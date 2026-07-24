@php
    $steps = [
        1 => ['label' => 'Подключение', 'route' => 'servers.wizard.connect'],
        2 => ['label' => 'Установка', 'route' => 'servers.wizard.install'],
        3 => ['label' => 'Базы данных', 'route' => 'servers.wizard.content'],
    ];
@endphp
<div class="flex flex-wrap gap-2 mb-8">
    @foreach ($steps as $num => $step)
        @php
            $done = $server->setup_step > $num || ($num === 2 && $server->is_setup_complete);
            $active = $current === $num;
            $class = $active ? 'step-pill-active' : ($done ? 'step-pill-done' : 'step-pill');
        @endphp
        @if ($done || $active || $num <= $server->setup_step)
            <a href="{{ route($step['route'], $server) }}" class="step-pill {{ $class }}">
                <span>{{ $num }}</span> {{ $step['label'] }}
            </a>
        @else
            <span class="step-pill">{{ $num }} {{ $step['label'] }}</span>
        @endif
    @endforeach
</div>
