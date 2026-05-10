<x-filament-panels::page>
    {{-- Month / Year filter bar --}}
    <div class="flex flex-wrap gap-3 items-center p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">الفترة:</span>

        <select
            wire:model.live="selectedMonth"
            class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-primary-500 focus:border-primary-500"
        >
            @foreach([1=>'يناير',2=>'فبراير',3=>'مارس',4=>'أبريل',5=>'مايو',6=>'يونيو',7=>'يوليو',8=>'أغسطس',9=>'سبتمبر',10=>'أكتوبر',11=>'نوفمبر',12=>'ديسمبر'] as $num => $name)
                <option value="{{ $num }}">{{ $name }}</option>
            @endforeach
        </select>

        <select
            wire:model.live="selectedYear"
            class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-primary-500 focus:border-primary-500"
        >
            @foreach(range(now()->year - 2, now()->year + 1) as $year)
                <option value="{{ $year }}">{{ $year }}</option>
            @endforeach
        </select>
    </div>

    {{-- Materials grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @forelse($this->getMaterials() as $material)
            <div class="flex flex-col gap-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                {{-- Name + monthly quantity --}}
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ $material->name }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $material->defaultUnit?->abbreviation ?? '—' }}</p>
                    </div>
                    <div class="text-end">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">توريد هذا الشهر</p>
                        <p class="text-lg font-bold text-warning-600 dark:text-warning-400">
                            {{ number_format($material->month_quantity ?? 0, 3) }}
                            {{ $material->defaultUnit?->abbreviation ?? '' }}
                        </p>
                    </div>
                </div>

                {{-- Single card action --}}
                <div>
                    <x-filament::button
                        size="sm"
                        color="warning"
                        icon="heroicon-m-arrow-down-tray"
                        wire:click="mountAction('recordDelivery', {{ \Illuminate\Support\Js::from(['material_type_id' => $material->id]) }})"
                    >
                        استلام مادة
                    </x-filament::button>
                </div>
            </div>
        @empty
            <div class="col-span-2 flex flex-col items-center justify-center py-16 text-gray-500 dark:text-gray-400">
                <x-filament::icon icon="heroicon-o-cube" class="h-12 w-12 mb-3 opacity-40" />
                <p>لا توجد أنواع مواد مسجلة. أضفها من صفحة أنواع المواد.</p>
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
