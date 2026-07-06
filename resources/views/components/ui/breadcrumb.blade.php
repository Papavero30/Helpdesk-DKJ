@php
    use App\Support\BreadcrumbStack;
    $crumbs = BreadcrumbStack::get();
    $parentCrumb = count($crumbs) >= 2 ? $crumbs[count($crumbs) - 2] : null;
@endphp

@if(count($crumbs) > 1)
<nav class="flex items-center gap-2 mb-5" aria-label="Breadcrumb">
    {{-- Compact icon-only back button (parent link) --}}
    @if($parentCrumb)
        <a href="{{ $parentCrumb['url'] }}" wire:navigate
           title="Back to {{ $parentCrumb['label'] }}"
           class="inline-flex items-center justify-center size-7 rounded-lg border border-gray-200 bg-white text-gray-500 hover:text-dongker-600 hover:border-dongker-300 transition shrink-0">
            <x-ui.icon name="arrow-left" class="size-3.5" />
        </a>
    @endif

    {{-- Breadcrumb trail --}}
    <div class="flex items-center gap-1.5 text-xs text-gray-500 min-w-0">
        <a href="/" wire:navigate class="flex items-center hover:text-dongker-600 transition-colors shrink-0">
            <x-ui.icon name="home" class="size-3.5" />
        </a>
        @foreach($crumbs as $idx => $crumb)
            <x-ui.icon name="chevron-right" class="size-3 text-gray-300 shrink-0" />
            @if($idx === count($crumbs) - 1)
                <span class="font-semibold text-dongker-600 truncate max-w-[200px]">{{ $crumb['label'] }}</span>
            @else
                <a href="{{ $crumb['url'] }}" wire:navigate
                   class="hover:text-dongker-600 transition-colors truncate max-w-[150px]">
                    {{ $crumb['label'] }}
                </a>
            @endif
        @endforeach
    </div>
</nav>
@endif
