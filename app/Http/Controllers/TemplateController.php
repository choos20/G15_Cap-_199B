<?php

namespace App\Http\Controllers;

use App\Exceptions\DocumentPreviewException;
use App\Models\Template;
use App\Services\DocumentPreviewService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Proposal templates — the blank forms initiators download, fill in and submit.
 *
 * Upload access is enforced by the `can.manage.templates` middleware.
 * Replacing and removing also check ownership, with CELP Head override.
 */
class TemplateController extends Controller
{
    public function __construct(private readonly DocumentPreviewService $documents) {}

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
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        return Template::with('uploader')
            ->when(trim($filters['q'] ?? '') !== '', function ($query) use ($filters) {
                $term = trim($filters['q']);
                $query->where(function ($query) use ($term) {
                    $query->where('original_name', 'like', '%'.$term.'%')
                        ->orWhereHas('uploader', fn ($query) => $query->where('name', 'like', '%'.$term.'%'));
                });
            })
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
            'term' => trim($request->query('q', '')),
            'sourceCounts' => $sourceCounts,
            'allCount' => Template::count(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(['template' => self::UPLOAD_RULES], self::UPLOAD_MESSAGES);
        $file = $request->file('template');
        if (Template::where('original_name', $file->getClientOriginalName())->exists()) {
            return back()->withErrors(['template' => 'A template with that file name already exists. Use Replace on the existing row to update it.'])->withInput();
        }
        $this->validateTemplateUpload($file);
        DB::transaction(fn () => $this->documents->storeUpload($file, new Template([
            'uploaded_by' => $request->user()->id,
            'source' => Template::sourceForRole($request->user()->role),
        ]), 'templates'));

        return redirect()->route('templates.manage')->with('status', 'Template uploaded.');
    }

    public function replace(Request $request, Template $template)
    {
        abort_unless($template->canBeManagedBy($request->user()), 403, 'You may only replace templates you uploaded.');
        $request->validate(['template' => self::UPLOAD_RULES], self::UPLOAD_MESSAGES);
        $file = $request->file('template');
        $this->validateTemplateUpload($file);
        try {
            $current = $this->documents->contents($template);
        } catch (DocumentPreviewException $error) {
            if ($error->getStatusCode() >= 500) {
                throw $error;
            }
            $current = null;
        }
        if ($current !== null && hash_equals(hash('sha256', $current), hash('sha256', $file->get()))) {
            return back()->withErrors(['template' => 'This is already the current file. Please choose a different file to replace it.'])->withInput();
        }
        $previous = clone $template;
        DB::transaction(fn () => $this->documents->storeUpload($file, $template, 'templates'));
        $this->documents->deleteStoredFile($previous);

        return redirect()->route('templates.manage')->with('status', 'Template replaced.');
    }

    public function destroy(Request $request, Template $template)
    {
        abort_unless($template->canBeManagedBy($request->user()), 403, 'You may only remove templates you uploaded.');
        $this->documents->deleteStoredFile($template);
        $template->delete();

        return redirect()->route('templates.manage')->with('status', 'Template removed successfully.');
    }

    public function preview(Request $request, Template $template)
    {
        abort_unless($request->user(), 403);

        return view('templates.preview', [
            'template' => $template,
            'extension' => strtoupper(pathinfo($template->original_name, PATHINFO_EXTENSION) ?: 'FILE'),
            'previewKind' => $this->documents->previewKind($template->original_name),
            'contentUrl' => route('templates.preview.content', $template),
        ]);
    }

    public function previewContent(Request $request, Template $template)
    {
        abort_unless($request->user(), 403);

        return $this->documents->response($template);
    }

    public function download(Request $request, Template $template)
    {
        abort_unless($request->user(), 403);

        return $this->documents->response($template, true);
    }

    private function validateTemplateUpload(UploadedFile $file): void
    {
        try {
            $this->documents->validateUpload($file);
        } catch (DocumentPreviewException $error) {
            if ($error->getStatusCode() >= 500) {
                throw $error;
            }
            throw ValidationException::withMessages(['template' => $error->getMessage()]);
        }
    }
}
