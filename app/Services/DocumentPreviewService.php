<?php

namespace App\Services;

use App\Exceptions\DocumentPreviewException;
use App\Models\ProjectAttachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use ZipArchive;

class DocumentPreviewService
{
    public const MAX_PREVIEW_BYTES = 10485760;

    public static function databaseValue(string $bytes, string $driver): string
    {
        // ASCII hex is safe for PDO::PARAM_STR; PostgreSQL decodes it into bytea.
        return $driver === 'pgsql' ? '\\x'.bin2hex($bytes) : $bytes;
    }

    public static function normalizeBinary(mixed $value): ?string
    {
        if (is_resource($value)) {
            $position = ftell($value);
            if (stream_get_meta_data($value)['seekable']) {
                rewind($value);
            }
            $bytes = stream_get_contents($value);
            if ($position !== false && stream_get_meta_data($value)['seekable']) {
                fseek($value, $position);
            }

            return is_string($bytes) ? $bytes : null;
        }

        if (! is_string($value)) {
            return null;
        }

        if (str_starts_with($value, '\\x') && strlen($value) > 2
            && strlen(substr($value, 2)) % 2 === 0 && ctype_xdigit(substr($value, 2))) {
            return hex2bin(substr($value, 2));
        }

        return $value;
    }

    public function name(Model $document): string
    {
        return (string) ($document instanceof ProjectAttachment ? $document->file_name : $document->original_name);
    }

    public function previewKind(string $name): string
    {
        return match (strtolower(pathinfo($name, PATHINFO_EXTENSION))) {
            'docx' => 'docx',
            'pdf' => 'pdf',
            'png', 'jpg', 'jpeg', 'webp' => 'image',
            'xls', 'xlsx' => 'spreadsheet',
            default => 'unsupported',
        };
    }

    public function previewMime(string $name): ?string
    {
        return match (strtolower(pathinfo($name, PATHINFO_EXTENSION))) {
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => null,
        };
    }

    public function contents(Model $document, bool $preview = false, ?string $sourceDirectory = null): string
    {
        $found = false;
        $lastError = null;
        foreach ($this->candidates($document, $sourceDirectory) as $bytes) {
            $found = true;
            try {
                $this->assertIntegrity($document, $bytes);
                if ($preview) {
                    $this->validatePreview($bytes, $this->name($document));
                }

                return $bytes;
            } catch (DocumentPreviewException $error) {
                if ($error->getStatusCode() >= 500 || $error->getStatusCode() === 413) {
                    throw $error;
                }
                $lastError = $error;
            }
        }

        if (! $found) {
            throw new DocumentPreviewException('missing', 'This document is missing from storage. An administrator can check backups for an intact copy.', 404);
        }

        throw $lastError ?? new DocumentPreviewException('corrupt', 'No intact copy of this document is available. An administrator can check backups for recovery.');
    }

    public function assertIntegrity(Model $document, string $bytes): void
    {
        if ($bytes === '') {
            throw new DocumentPreviewException('empty', 'The stored document is empty. An administrator can check for an intact backup.');
        }

        if ($document->file_size !== null && (int) $document->file_size > 0 && strlen($bytes) !== (int) $document->file_size) {
            throw new DocumentPreviewException('size_mismatch', 'The stored document does not match its recorded file size. An administrator can check for an intact copy.');
        }

        if ($document->file_sha256 && ! hash_equals((string) $document->file_sha256, hash('sha256', $bytes))) {
            throw new DocumentPreviewException('checksum_mismatch', 'The stored document failed its SHA-256 integrity check. An administrator can check backups for recovery.');
        }
    }

    public function validatePreview(string $bytes, string $name): void
    {
        if ($this->previewKind($name) === 'unsupported') {
            throw new DocumentPreviewException('unsupported', 'This file type is available for download only.', 415);
        }
        if (strlen($bytes) > self::MAX_PREVIEW_BYTES) {
            throw new DocumentPreviewException('too_large', 'This document is too large for the browser preview. Download the original file instead.', 413);
        }

        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (in_array($extension, ['docx', 'xlsx'], true)) {
            $this->validateOfficePackage($bytes, $extension);
        }
    }

    public function validateUpload(UploadedFile $file): void
    {
        $extension = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
        if (in_array($extension, ['docx', 'xlsx'], true)) {
            $this->validateOfficePackage($file->get(), $extension);
        }
    }

    public function storeUpload(UploadedFile $file, Model $document, string $directory): Model
    {
        $bytes = $file->get();
        if (! is_string($bytes) || $bytes === '' || strlen($bytes) !== $file->getSize()) {
            throw new DocumentPreviewException('upload_incomplete', 'The uploaded file could not be read completely. Please retry the upload.');
        }
        $this->validateUpload($file);

        if (! Schema::hasColumn($document->getTable(), 'file_data')) {
            throw new DocumentPreviewException('storage_not_ready', 'Document storage needs the latest database migration. No attachment was accepted.', 503);
        }

        $name = str_replace(["\r", "\n", "\0"], '', basename(str_replace('\\', '/', $file->getClientOriginalName())));
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $path = trim($directory, '/').'/'.Str::uuid().($extension ? '.'.$extension : '');
        $mime = $this->previewMime($name) ?? $file->getMimeType() ?? 'application/octet-stream';
        $hash = hash('sha256', $bytes);

        if (! Storage::disk('local')->put($path, $bytes)
            || ! hash_equals($hash, hash('sha256', Storage::disk('local')->get($path)))) {
            throw new DocumentPreviewException('storage_failed', 'The document could not be stored completely. Please retry later.', 503);
        }

        try {
            $document->getConnection()->transaction(function () use ($document, $name, $path, $bytes, $mime, $hash): void {
                $document->setAttribute($document instanceof ProjectAttachment ? 'file_name' : 'original_name', $name);
                $document->file_path = $path;
                $document->file_data = $bytes;
                $document->file_mime = $mime;
                if ($document instanceof ProjectAttachment) {
                    $document->mime_type = $mime;
                }
                $this->setMetadata($document, strlen($bytes), $hash);
                $document->save();
                $document->refresh();
                $roundTrip = $document->file_data;
                if (! is_string($roundTrip) || ! hash_equals($hash, hash('sha256', $roundTrip))) {
                    throw new DocumentPreviewException('storage_failed', 'The database did not preserve the complete document. The upload was not accepted.', 503);
                }
            });
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($path);
            if ($error instanceof QueryException) {
                $this->databaseFailure($document, $error);
            }
            throw $error;
        }

        return $document;
    }

    public function response(Model $document, bool $download = false)
    {
        $name = $this->name($document);
        if (! $download && $this->previewMime($name) === null) {
            throw new DocumentPreviewException('unsupported', 'This file type is available for download only.', 415);
        }
        $bytes = $this->contents($document, ! $download);
        $fallback = str_replace('%', '_', preg_replace('/[^\x20-\x7E]/', '_', Str::ascii($name))) ?: 'document';

        return response($bytes, 200, [
            'Content-Type' => $this->previewMime($name) ?? $document->file_mime ?? 'application/octet-stream',
            'Content-Disposition' => HeaderUtils::makeDisposition($download ? 'attachment' : 'inline', $name, $fallback),
            'Content-Length' => (string) strlen($bytes),
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow',
            'X-Document-SHA256' => hash('sha256', $bytes),
        ]);
    }

    public function deleteStoredFile(Model $document): bool
    {
        if (! $document->file_path) {
            return true;
        }
        foreach ($document->file_disk ? [$document->file_disk] : ['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($document->file_path)
                && ! Storage::disk($disk)->delete($document->file_path)) {
                return false;
            }
        }

        return true;
    }

    public function repair(Model $document, ?string $sourceDirectory = null, ?string $sourceHash = null): string
    {
        $bytes = $this->contents($document, $this->previewKind($this->name($document)) !== 'unsupported', $sourceDirectory);
        $hash = hash('sha256', $bytes);
        if ($sourceHash && ! hash_equals(strtolower($sourceHash), $hash)) {
            throw new DocumentPreviewException('source_mismatch', 'The recovery source does not match the supplied SHA-256 checksum. No document was changed.');
        }
        $path = $document->file_path ?: 'documents/'.Str::uuid();
        if (! Storage::disk('local')->put($path, $bytes)
            || ! hash_equals($hash, hash('sha256', Storage::disk('local')->get($path)))) {
            throw new DocumentPreviewException('storage_failed', 'Could not verify the private recovery copy. No public copy was removed.', 503);
        }

        try {
            $document->getConnection()->transaction(function () use ($document, $bytes, $path, $hash): void {
                $document->file_path = $path;
                $document->file_data = $bytes;
                $document->file_mime = $this->previewMime($this->name($document)) ?? $document->file_mime ?? $document->mime_type;
                $this->setMetadata($document, strlen($bytes), $hash);
                $document->save();
                $document->refresh();
                if (! hash_equals($hash, hash('sha256', $document->file_data ?? ''))) {
                    throw new DocumentPreviewException('storage_failed', 'The recovered database copy failed verification.', 503);
                }
            });
        } catch (QueryException $error) {
            $this->databaseFailure($document, $error);
        }

        // Move legacy public files only after both private and database copies
        // have been verified. Preserve any differing old copy in quarantine.
        if (Storage::disk('public')->exists($path)) {
            $oldBytes = Storage::disk('public')->get($path);
            if (! hash_equals($hash, hash('sha256', $oldBytes))) {
                $quarantine = 'document_recovery/'.$document->getTable().'-'.$document->getKey().'-'.Str::uuid();
                if (! Storage::disk('local')->put($quarantine, $oldBytes)
                    || ! hash_equals(hash('sha256', $oldBytes), hash('sha256', Storage::disk('local')->get($quarantine)))) {
                    throw new DocumentPreviewException('storage_failed', 'The previous public copy could not be preserved safely.', 503);
                }
            }
            if (! Storage::disk('public')->delete($path)) {
                throw new DocumentPreviewException('storage_failed', 'The verified document is recovered, but the old public copy could not be removed.', 503);
            }
        }

        return $hash;
    }

    private function setMetadata(Model $document, int $size, string $hash): void
    {
        foreach (['file_size' => $size, 'file_sha256' => $hash, 'file_disk' => 'local'] as $column => $value) {
            if (Schema::hasColumn($document->getTable(), $column)) {
                $document->setAttribute($column, $value);
            }
        }
    }

    private function databaseFailure(Model $document, QueryException $error): never
    {
        // QueryException embeds bound document bytes in its message. Never
        // expose that message or chain it into the application's error log.
        Log::warning('Document database write failed.', [
            'table' => $document->getTable(), 'sqlstate' => $error->errorInfo[0] ?? $error->getCode(),
        ]);
        throw new DocumentPreviewException('storage_failed', 'The document could not be saved to the database. Please retry later.', 503);
    }

    private function candidates(Model $document, ?string $sourceDirectory): \Generator
    {
        $binary = self::normalizeBinary($document->getRawOriginal('file_data'));
        if ($binary !== null) {
            yield $binary;
        }
        if (! $document->file_path) {
            return;
        }
        foreach (array_unique(array_filter([$document->file_disk, 'local', 'public'])) as $disk) {
            if (Storage::disk($disk)->exists($document->file_path)) {
                yield Storage::disk($disk)->get($document->file_path);
            }
        }
        if ($sourceDirectory) {
            $root = realpath($sourceDirectory);
            $path = realpath(rtrim($sourceDirectory, '/\\').'/'.$document->file_path);
            if ($root && $path && str_starts_with($path, $root.DIRECTORY_SEPARATOR) && is_file($path)) {
                yield file_get_contents($path);
            }
        }
    }

    private function validateOfficePackage(string $bytes, string $extension): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new DocumentPreviewException('validator_unavailable', 'Document validation is temporarily unavailable on the server.', 503);
        }
        if (! str_starts_with($bytes, "PK\x03\x04")) {
            throw new DocumentPreviewException('corrupt', 'This document is not an intact Office document. An administrator can check for a recoverable copy.');
        }
        $path = @tempnam(sys_get_temp_dir(), 'projectlink-document-');
        if ($path === false) {
            throw new DocumentPreviewException('validator_unavailable', 'The server could not prepare document validation. Please try again later.', 503);
        }
        $zip = new ZipArchive;
        $opened = false;
        try {
            if (@file_put_contents($path, $bytes) !== strlen($bytes)) {
                throw new DocumentPreviewException('validator_unavailable', 'The server could not prepare document validation.', 503);
            }
            $opened = @$zip->open($path, ZipArchive::CHECKCONS) === true;
            if (! $opened || $zip->numFiles === 0) {
                throw new DocumentPreviewException('corrupt', 'The document archive is incomplete or corrupted. An administrator can check for an intact copy.');
            }
            $mainPart = $extension === 'docx' ? 'word/document.xml' : 'xl/workbook.xml';
            foreach (['[Content_Types].xml', '_rels/.rels', $mainPart] as $required) {
                if ($zip->locateName($required) === false) {
                    throw new DocumentPreviewException('corrupt', 'The Office document is missing a required internal component.');
                }
            }
            if ($zip->numFiles > 2048) {
                throw new DocumentPreviewException('too_complex', 'This document has too many internal components for a browser preview. Download it instead.', 413);
            }
            $total = 0;
            $names = [];
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                if ($stat === false || isset($names[$stat['name']])) {
                    throw new DocumentPreviewException('corrupt', 'The document contains invalid or duplicate archive entries.');
                }
                $names[$stat['name']] = true;
                $total += $stat['size'];
                if ($total > 104857600) {
                    throw new DocumentPreviewException('too_complex', 'This document expands beyond the browser preview limit. Download it instead.', 413);
                }
                if (str_ends_with($stat['name'], '/')) {
                    continue;
                }
                $stream = @$zip->getStream($stat['name']);
                if ($stream === false) {
                    throw new DocumentPreviewException('corrupt', 'The document contains an unreadable archive component.');
                }
                try {
                    $crc = hash_init('crc32b');
                    $count = @hash_update_stream($crc, $stream, $stat['size'] + 1);
                    if ($count !== $stat['size'] || hash_final($crc) !== sprintf('%08x', $stat['crc'])) {
                        throw new DocumentPreviewException('corrupt', 'The document contains a damaged archive component.');
                    }
                } finally {
                    fclose($stream);
                }
            }
            $previous = libxml_use_internal_errors(true);
            try {
                foreach (['[Content_Types].xml', '_rels/.rels', $mainPart] as $part) {
                    $xml = new \DOMDocument;
                    $partBytes = @$zip->getFromName($part);
                    if (! is_string($partBytes) || $partBytes === '' || ! $xml->loadXML($partBytes, LIBXML_NONET) || $xml->doctype !== null) {
                        throw new DocumentPreviewException('corrupt', 'The document contains invalid internal XML.');
                    }
                }
                if ($xml->documentElement?->localName !== ($extension === 'docx' ? 'document' : 'workbook')) {
                    throw new DocumentPreviewException('corrupt', 'The document does not contain the expected Office document structure.');
                }
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
        } finally {
            if ($opened) {
                $zip->close();
            }
            @unlink($path);
        }
    }
}
