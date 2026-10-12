<?php

namespace App\Events;

use App\Models\Sale;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderPlaced
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  list<string>  $ownerTypes  Category owners involved in the order.
     */
    public function __construct(
        public Sale $sale,
        public array $ownerTypes,
    ) {}
}
