@aware(['maxRows'=>null,'label'=>null])
<div class="bg-white flex items-center justify-between dark:bg-neutral-900 px-4 py-2 border-b border-neutral-800/10 dark:border-white/10">
    <div>
    @if($label)
        <label class="block text-sm font-semibold text-neutral-700 dark:text-neutral-300 ">
            {{ $label }}
        </label>
    @endif
    </div>
    <div class="flex justify-end items-center gap-2">
        @if(filled($maxRows))
            <div class="text-xs text-neutral-500 dark:text-neutral-400">
                <span x-text="getRowCount()"></span> / {{ $maxRows }} rows
            </div>
        @endif

        <button 
            type="button" 
            x-on:click="clearAll()" 
            class="text-sm hover:opacity-70 transition-opacity cursor-pointer text-neutral-400 dark:text-neutral-300 duration-300 cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed">
            <x-ui.icon name="trash" class="size-4" />
        </button>
    </div>
</div>