@props([
    'fileName' => null,
    'extension' => null,
    'size' => 42,
    'label' => null,
])

@php
    $resolvedExtension = strtolower(trim((string) ($extension ?: pathinfo((string) $fileName, PATHINFO_EXTENSION))));
    $isPdf = $resolvedExtension === 'pdf';
    $isWord = in_array($resolvedExtension, ['doc', 'docx'], true);
    $isExcel = in_array($resolvedExtension, ['xls', 'xlsx'], true);
    $documentLabel = $label ?: match (true) {
        $isPdf => 'PDF document',
        $isWord => 'Word document',
        $isExcel => 'Excel spreadsheet',
        default => 'Document',
    };
@endphp

@if($isPdf || $isWord || $isExcel)
    <img
        src="{{ asset(match (true) {
            $isPdf => 'images/document-icons/pdf.png',
            $isWord => 'images/document-icons/word.png',
            default => 'images/document-icons/excel.svg',
        }) }}"
        alt="{{ $documentLabel }}"
        width="{{ (int) $size }}"
        height="{{ (int) $size }}"
        style="display:block; width:{{ (int) $size }}px; height:{{ (int) $size }}px; object-fit:contain; flex:0 0 auto;"
    >
@else
    <span
        role="img"
        aria-label="{{ $documentLabel }}"
        style="display:flex; align-items:center; justify-content:center; width:{{ (int) $size }}px; height:{{ (int) $size }}px; flex:0 0 auto; border-radius:10px; background:#f1f5f9; color:#475569;"
    >
        <x-ui-icon name="file" :size="max(16, (int) round($size * 0.52))" />
    </span>
@endif
