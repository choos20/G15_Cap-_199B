<?php

namespace App\Http\Controllers;

use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Proposal templates — the blank forms initiators download, fill in and submit.
 *
 * Upload access is enforced by the `can.manage.templates` middleware.
 * Replacing and removing also check ownership, with CELP Head override.
 */
class TemplateController extends Controller
{
    /**
     * Upload rules, shared by store() and replace().
     *
     * `mimes` alone trusts the extension, so a renamed .exe would pass. The
     * `mimetypes` rule inspects the file's actual content type, and both must
     * agree before the file is stored.
     */
    private const UPLOAD_RULES = [
        'required', 'file', 'max:10240',
        'mimes:pdf,doc,docx',
        'mimetypes:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    private const UPLOAD_MESSAGES = [
        'template.mimes' => 'Templates must be a PDF, DOC or DOCX file.',
        'template.mimetypes' => 'That file is not a real PDF, DOC or DOCX, whatever its extension says.',
        'template.max' => 'Templates must be 10MB or smaller.',
        'template.required' => 'Please choose a file to upload.',
    ];

    private function allTemplates()
    {
        return Template::with('uploader')->orderByDesc('created_at')->get();
    }

    private function filteredTemplates(Request $request)
    {
        $filters = $request->validate([
            'source' => ['nullable', 'in:celp,csa,unknown'],
        ]);

        return Template::with('uploader')
            ->when(isset($filters['source']), function ($query) use ($filters) {
                if ($filters['source'] === 'unknown') {
                    $query->whereNull('source');
                } else {
                    $query->where('source', $filters['source']);
                }
            })
            ->orderByDesc('created_at')
            ->get();
    }

    /** Manage view — upload, replace and remove. Gated by middleware. */
    public function manage(Request $request)
    {
        return view('templates.manage', ['templates' => $this->allTemplates()]);
    }

    /**
     * Read-only list for everyone else: initiators, students, employees and
     * alumni all need these forms.
     */
    public function index(Request $request)
    {
        // Managers landing here get the page they can actually act on.
        if ($request->user()->canManageTemplates()) {
            return redirect()->route('templates.manage');
        }

        $templates = $this->filteredTemplates($request);
        $sourceCounts = Template::query()
            ->selectRaw('source, COUNT(*) as total')
            ->groupBy('source')
            ->pluck('total', 'source');

        return view('templates.list', [
            'templates' => $templates,
            'source' => $request->query('source'),
            'sourceCounts' => $sourceCounts,
            'allCount' => Template::count(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(['template' => self::UPLOAD_RULES], self::UPLOAD_MESSAGES);

        $file = $request->file('template');
        $originalName = $file->getClientOriginalName();
        $storedPath = $file->store('templates', 'public');
        $attributes = [
            'original_name' => $originalName,
            'file_path' => $storedPath,
            'uploaded_by' => $request->user()->id,
            'source' => Template::sourceForRole($request->user()->role),
        ];

        if ($this->ensureFileDataColumns()) {
            $attributes['file_data'] = $this->binaryValue($file->get());
            $attributes['file_mime'] = $file->getMimeType();
        }

        if (Template::where('original_name', $originalName)->exists()) {
            return back()
                ->withErrors(['template' => 'A template with that file name already exists. Use Replace on the existing row to update it.'])
                ->withInput();
        }

        Template::create($attributes);

        return redirect()->route('templates.manage')->with('status', 'Template uploaded.');
    }

    /**
     * Swap the file behind an existing template without changing its owner or
     * source. CELP Head may replace another manager's template without taking
     * ownership away from its original uploader.
     */
    public function replace(Request $request, Template $template)
    {
        abort_unless($template->canBeManagedBy($request->user()), 403, 'You may only replace templates you uploaded.');

        $request->validate(['template' => self::UPLOAD_RULES], self::UPLOAD_MESSAGES);

        $file = $request->file('template');
        $newContents = $file->get();

        if ($this->isSameTemplateFile($template, $newContents)) {
            return back()
                ->withErrors(['template' => 'This is already the current file. Please choose a different file to replace it.'])
                ->withInput();
        }

        $oldPath = $template->file_path;
        $newPath = $file->store('templates', 'public');
        $attributes = [
            'original_name' => $file->getClientOriginalName(),
            'file_path' => $newPath,
        ];

        if ($this->ensureFileDataColumns()) {
            $attributes['file_data'] = $this->binaryValue($newContents);
            $attributes['file_mime'] = $file->getMimeType();
        }

        $template->update($attributes);

        // Only drop the previous file once the new one is safely recorded.
        if ($oldPath && $oldPath !== $template->file_path) {
            Storage::disk('public')->delete($oldPath);
        }

        return redirect()->route('templates.manage')->with('status', 'Template replaced.');
    }

    public function destroy(Request $request, Template $template)
    {
        abort_unless($template->canBeManagedBy($request->user()), 403, 'You may only remove templates you uploaded.');

        Storage::disk('public')->delete($template->file_path);
        $template->delete();

        return redirect()->route('templates.manage')->with('status', 'Template removed successfully.');
    }

    /** Downloading is open to every authenticated user. */
    public function download(Request $request, Template $template)
    {
        if ($this->ensureFileDataColumns() && $template->file_data) {
            $data = $template->file_data;

            if (is_resource($data)) {
                $data = stream_get_contents($data);
            }
            if (is_string($data) && str_starts_with($data, '\\x')) {
                $data = hex2bin(substr($data, 2));
            }

            return response()->streamDownload(
                static function () use ($data): void {
                    echo $data;
                },
                $template->original_name,
                ['Content-Type' => $template->file_mime ?: 'application/octet-stream']
            );
        }

        if (! $template->file_path || ! Storage::disk('public')->exists($template->file_path)) {
            abort(404);
        }

        return Storage::disk('public')->download($template->file_path, $template->original_name);
    }

    private function ensureFileDataColumns(): bool
    {
        if (Schema::hasColumn('templates', 'file_data')) {
            return true;
        }

        Schema::table('templates', function ($table): void {
            $table->binary('file_data')->nullable();
            $table->string('file_mime', 150)->nullable();
        });

        return true;
    }

    private function binaryValue(string $data): mixed
    {
        return DB::raw("decode('" . base64_encode($data) . "', 'base64')");
    }

    private function isSameTemplateFile(Template $template, string $newContents): bool
    {
        $currentContents = $this->templateContents($template);

        return $currentContents !== null
            && hash('sha256', $currentContents) === hash('sha256', $newContents);
    }

    private function templateContents(Template $template): ?string
    {
        if ($this->ensureFileDataColumns() && $template->file_data) {
            $data = $template->file_data;

            if (is_resource($data)) {
                $data = stream_get_contents($data);
            }
            if (is_string($data) && str_starts_with($data, '\\x')) {
                $data = hex2bin(substr($data, 2));
            }

            return is_string($data) ? $data : null;
        }

        if ($template->file_path && Storage::disk('public')->exists($template->file_path)) {
            return Storage::disk('public')->get($template->file_path);
        }

        return null;
    }
}
