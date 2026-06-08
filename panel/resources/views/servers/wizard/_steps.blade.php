@php
    $current = $current ?? 1;
    $steps = [
        1 => 'SSH + облако',
        2 => 'Конфиги',
        3 => 'Базы данных',
        4 => 'Проекты',
    ];
@endphp

<ol class="flex flex-wrap gap-2 mb-8">
    @foreach ($steps as $num => $label)
        @php
            $isActive = $current === $num;
            $isDone = $current > $num;
            $canVisit = isset($server) && $server->canAccessWizardStep($num);
            $pillClass = 'step-pill inline-flex items-center gap-2 '
                .($isActive ? 'step-pill-active' : ($isDone ? 'step-pill-done' : ''))
                .($canVisit && !$isActive ? ' step-pill-link' : '');
        @endphp
        <li>
            @if($canVisit)
                <a href="{{ route($server->wizardStepRouteName($num), $server) }}"
                   class="{{ $pillClass }}"
                   @if($isActive) aria-current="step" @endif>
                    <span class="w-5 h-5 rounded-full flex items-center justify-center text-xs
                        {{ $isActive ? 'bg-brand-600 text-white' : ($isDone || $num <= $server->maxAccessibleWizardStep() ? 'bg-slate-200 text-slate-600' : 'bg-slate-100 text-slate-400') }}">
                        {{ $num }}
                    </span>
                    <span>{{ $label }}</span>
                </a>
            @else
                <span class="{{ $pillClass }}" @if($isActive) aria-current="step" @endif>
                    <span class="w-5 h-5 rounded-full flex items-center justify-center text-xs
                        {{ $isActive ? 'bg-brand-600 text-white' : ($isDone ? 'bg-slate-200 text-slate-600' : 'bg-slate-100 text-slate-400') }}">
                        {{ $num }}
                    </span>
                    <span>{{ $label }}</span>
                </span>
            @endif
        </li>
    @endforeach
</ol>
