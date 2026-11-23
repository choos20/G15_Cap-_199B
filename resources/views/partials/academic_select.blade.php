@php
    $academicValue = old($academicField, $academicCurrent ?? '');
    $options = $academicChoices[$academicField === 'affiliation' ? 'departments' : 'programs'];
    $academicValue = is_string($academicValue) ? \App\Support\AcademicCatalog::canonical($academicValue, $options) : '';
@endphp
<select id="{{ $academicField }}" name="{{ $academicField }}" @if($academicField === 'program') aria-describedby="program-help" @endif>
    <option value="">{{ $academicField === 'affiliation' ? 'Choose college / department / office' : 'Choose program' }}</option>
    @foreach($options as $academicOption)
        <option value="{{ $academicOption->code }}" @selected($academicValue === $academicOption->code)
            data-academic-name="{{ $academicOption->name }}"
            @if($academicField === 'program') data-department="{{ $academicOption->department }}" @endif>{{ $academicOption->code }}{{ empty($academicCompact) && $academicOption->name !== $academicOption->code ? ' — '.$academicOption->name : '' }}</option>
    @endforeach
</select>
@if(!empty($academicCompact))
    <div class="cu-academic-name">
        <span>{{ $academicField === 'affiliation' ? 'College / department name' : 'Program name' }}</span>
        <strong data-academic-selection="{{ $academicField }}">{{ $options->firstWhere('code', $academicValue)?->name ?: 'Select an option above to see its full name.' }}</strong>
    </div>
@endif
@if($academicField === 'program')
    <div class="hint" id="program-help" data-program-help role="status">Choose a participant type and college / department to select a program.</div>
@endif
