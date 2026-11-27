<?php

namespace App\Console\Commands;

use App\Exceptions\DocumentPreviewException;
use App\Models\AuditLog;
use App\Models\ProjectAttachment;
use App\Models\Template;
use App\Services\DocumentPreviewService;
use Illuminate\Console\Command;

class AuditDocuments extends Command
{
    protected $signature = 'documents:audit
        {--repair : Persist verified copies and move legacy public files into private storage}
        {--type= : Restrict to project or template documents}
        {--id=* : Restrict to these document IDs}
        {--source-directory= : Optional trusted backup root containing the recorded relative file paths}
        {--sha256= : Require this source checksum when repairing one document}';

    protected $description = 'Check document sizes, checksums and Office integrity; optionally recover verified copies without deleting document records';

    public function handle(DocumentPreviewService $documents): int
    {
        $type = $this->option('type');
        $ids = $this->option('id');
        $checksum = $this->option('sha256');
        if (($type && ! in_array($type, ['project', 'template'], true))
            || array_filter($ids, fn ($id) => ! ctype_digit((string) $id))
            || ($checksum && (! preg_match('/^[a-f0-9]{64}$/i', $checksum) || count($ids) !== 1 || ! $type))) {
            $this->error('Use project/template, numeric IDs, and a SHA-256 checksum only with one document ID and type.');

            return self::INVALID;
        }
        if ($this->option('source-directory') && ! is_dir($this->option('source-directory'))) {
            $this->error('The trusted backup directory does not exist.');

            return self::INVALID;
        }

        $rows = [];
        $seen = [];
        $unresolved = 0;
        foreach (['project' => ProjectAttachment::class, 'template' => Template::class] as $kind => $model) {
            if ($type && $type !== $kind) {
                continue;
            }
            foreach ($model::query()->when($ids, fn ($query) => $query->whereIn('id', $ids))->orderBy('id')->cursor() as $document) {
                $seen[] = (string) $document->id;
                try {
                    $bytes = $documents->contents($document, $documents->previewKind($documents->name($document)) !== 'unsupported', $this->option('source-directory'));
                    $hash = hash('sha256', $bytes);
                    if ($checksum && ! hash_equals(strtolower($checksum), $hash)) {
                        throw new DocumentPreviewException('source_mismatch', 'The recovery source does not match the supplied checksum.');
                    }
                    $status = 'valid';
                    if ($this->option('repair')) {
                        $old = DocumentPreviewService::normalizeBinary($document->getRawOriginal('file_data'));
                        $hash = $documents->repair($document, $this->option('source-directory'), $checksum);
                        $status = is_string($old) && hash_equals(hash('sha256', $old), $hash) ? 'verified / private' : 'recovered / private';
                        AuditLog::record('document.recovered', 'Verified and persisted a private document copy.', $document, [
                            'document_type' => $kind, 'bytes' => strlen($bytes), 'sha256' => $hash, 'result' => $status,
                        ]);
                    }
                    $rows[] = [$kind, $document->id, $status, strlen($bytes), $hash];
                } catch (DocumentPreviewException $error) {
                    $rows[] = [$kind, $document->id, $error->reason, '-', '-'];
                    $unresolved++;
                }
            }
        }
        foreach (array_diff($ids, $seen) as $missingId) {
            $rows[] = [$type ?: 'either', $missingId, 'record_missing', '-', '-'];
            $unresolved++;
        }
        $this->table(['Type', 'ID', 'Result', 'Bytes', 'SHA-256'], $rows);
        $this->line($this->option('repair') ? 'Verified copies were persisted. Unrecoverable records were retained unchanged.' : 'Read-only audit. Use --repair to persist verified recovery copies.');

        return $unresolved ? self::FAILURE : self::SUCCESS;
    }
}
