<?php

namespace App\Filament\Widgets;

use App\Models\Client;
use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $orders = Order::with('payments.cashEntry')->get();
        $totalRemaining = $orders->sum(fn ($o) => $o->remaining);
        $pendingCount   = $orders->filter(fn ($o) => $o->remaining > 0)->count();

        return [
            Stat::make('إجمالي العملاء', Client::count())
                ->icon('heroicon-o-users')
                ->color('info'),

            Stat::make('طلبات معلقة', $pendingCount)
                ->icon('heroicon-o-clock')
                ->color('warning'),

            Stat::make('إجمالي الديون', number_format($totalRemaining, 2) . ' ج.م')
                ->icon('heroicon-o-banknotes')
                ->color('danger'),
        ];
    }
}
