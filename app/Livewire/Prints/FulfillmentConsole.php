<?php

namespace App\Livewire\Prints;

use App\Models\PrintOrder;
use App\Prints\Enums\PrintOrderStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The counter's tablet: one screen a staff member opens and works from.
 *
 * This is the "press an icon on a tablet and launch a super simple tool" of
 * the client's post. The queue polls itself, so new photos a customer sends
 * from the sofa by the window simply appear; a card carries everything the
 * counter needs -- the code to call out, the photos with their print counts,
 * whether payment is owed at pickup -- and the actions are big enough to hit
 * with one thumb while the other hand holds the print tray.
 */
class FulfillmentConsole extends Component
{
    /**
     * The lab's printers, as the staff know them.
     *
     * Up to three, per the requirement; the keys are what a print job is
     * stamped with, the values what the buttons say.
     *
     * @var array<string, string>
     */
    public const array PRINTERS = [
        'front' => 'Front counter',
        'lab' => 'Back lab',
        'wide' => 'Wide format',
    ];

    /**
     * The queue tab being worked. Defaults to what needs a decision next.
     */
    public string $tab = PrintOrderStatus::Received->value;

    /** @var array<int, string> */
    public array $selectedPrinter = [];

    /**
     * Send an order's photos to one of the printers.
     *
     * Every item is stamped with the printer and the moment it was sent, so
     * the card that follows (and the panel's order view) can tell a printed
     * photo from one still waiting.
     */
    public function sendToPrinter(int $orderId): void
    {
        $order = $this->orderInState($orderId, PrintOrderStatus::Received);

        if ($order === null) {
            return;
        }

        $printer = $this->selectedPrinter[$orderId] ?? array_key_first(self::PRINTERS);

        if (! array_key_exists($printer, self::PRINTERS)) {
            $printer = array_key_first(self::PRINTERS);
        }

        DB::transaction(function () use ($order, $printer): void {
            $order->items()->update([
                'printer' => $printer,
                'printed_at' => now(),
            ]);

            $order->transitionTo(PrintOrderStatus::Printing);
        });
    }

    /**
     * Prints are done: bag them for pickup, or pack them for the post.
     */
    public function markReady(int $orderId): void
    {
        $order = $this->orderInState($orderId, PrintOrderStatus::Printing);

        $order?->transitionTo(PrintOrderStatus::Ready);
    }

    /**
     * Collected at the counter, or handed to the courier.
     */
    public function markCompleted(int $orderId): void
    {
        $order = $this->orderInState($orderId, PrintOrderStatus::Ready);

        $order?->transitionTo(PrintOrderStatus::Completed);
    }

    /**
     * Show one status's queue, oldest first: the family that arrived first is
     * the family whose photos print first.
     *
     * @return Collection<int, PrintOrder>
     */
    #[Computed]
    public function orders()
    {
        return PrintOrder::query()
            ->with(['items.media', 'location'])
            ->where('status', $this->tab)
            ->orderBy('created_at')
            ->limit(30)
            ->get();
    }

    /**
     * The count beside each tab, so the queue's shape is visible before a
     * card is opened.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        $statuses = array_map(
            fn (PrintOrderStatus $status): string => $status->value,
            PrintOrderStatus::cases(),
        );

        return PrintOrder::query()
            ->selectRaw('status, count(*) as aggregate')
            ->whereIn('status', $statuses)
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();
    }

    /**
     * The tabs in working order, with their badges resolved.
     *
     * @return list<array{value: string, label: string, count: int}>
     */
    #[Computed]
    public function tabs(): array
    {
        $counts = $this->counts();

        // Built by appending rather than collect()->map() so the list type
        // is carried literally: the tabs render in exactly this order.
        $tabs = [];

        foreach (PrintOrderStatus::cases() as $status) {
            $tabs[] = [
                'value' => $status->value,
                'label' => $status->label(),
                'count' => (int) ($counts[$status->value] ?? 0),
            ];
        }

        return $tabs;
    }

    /**
     * Fetch an order in the state an action expects, or null: a second tablet
     * (or a stale tab) may have moved the order on already, and the right
     * answer to that is an action that quietly does nothing while the poll
     * re-renders the truth -- not an error over a counter.
     */
    private function orderInState(int $orderId, PrintOrderStatus $expected): ?PrintOrder
    {
        $order = PrintOrder::query()->with('items')->find($orderId);

        if ($order === null || $order->status !== $expected) {
            return null;
        }

        return $order;
    }

    public function render(): View
    {
        return view('livewire.prints.fulfillment-console')->layout('layouts.kiosk');
    }
}
