<?php

namespace Thunk\Verbs\Testing;

use Illuminate\Support\LazyCollection;
use Illuminate\Support\Testing\Fakes\Fake;
use PHPUnit\Framework\Assert;
use Thunk\Verbs\Contracts\Store as StoreContract;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Snowflake;

class StoreFake implements StoreContract, Fake
{
    protected array $saved = [];

    public function assertSaved(string $event_type)
    {
        Assert::assertTrue($this->get([$event_type])->isNotEmpty());
    }
	
	public function assertNothingSaved()
	{
		Assert::assertEmpty($this->saved);
	}

    public function save(Event $event): string
    {
        $this->saved[] = $event;

        return Snowflake::id();
    }

    /** @return LazyCollection<int, \Thunk\Verbs\Event> */
    public function get(?array $event_types = null, int $chunk_size = 1000): LazyCollection
    {
        return LazyCollection::make($this->saved)
            ->when($event_types, fn ($query) => $query->filter(fn (Event $event) => in_array($event::class, $event_types)));
    }
}
