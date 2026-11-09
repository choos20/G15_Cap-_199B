<?php

namespace App\Http\Controllers;

use App\Models\Template;
use Illuminate\Http\Request;
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

        if (Template::where('original_name', $originalName)->exists()) {
            return back()
                ->withErrors(['template' => 'A template with that file name already exists. Use Replace on the existing row to update it.'])
                ->withInput();
        }

        Template::create([
            'original_name' => $originalName,
            'file_path' => $file->store('templates', 'public'),
            'uploaded_by' => $request->user()->id,
            'source' => Template::sourceForRole($request->user()->role),
        ]);

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
        $oldPath = $template->file_path;

        $template->update([
            'original_name' => $file->getClientOriginalName(),
            'file_path' => $file->store('templates', 'public'),
        ]);

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
        $path = storage_path('app/public/' . $template->file_path);

        if (! file_exists($path)) {
            abort(404);
        }

        return response()->download($path, $template->original_name);
    }
}
