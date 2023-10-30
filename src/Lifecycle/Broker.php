<?php

namespace Thunk\Verbs\Lifecycle;

use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\Queue as EventQueue;
use Thunk\Verbs\Models\VerbEvent;

class Broker
{
    public bool $is_replaying = false;

    public function fire(Event $event)
    {
        $states = collect($event->states());

        $states->each(fn ($state) => Guards::for($event, $state)->check());
        $states->each(fn ($state) => app(Dispatcher::class)->apply($event, $state));

        app(Queue::class)->queue($event);

        $event->fired = true;

        return $event;
    }

    public function commit(): bool
    {
        $events = app(EventQueue::class)->flush();

        // FIXME: Only write changes + handle aggregate versioning
        app(StateStore::class)->writeLoaded();

        if (empty($events)) {
            return true;
        }

        foreach ($events as $event) {
            app(Dispatcher::class)->fire($event);
        }

        return $this->commit();
    }

    public function replay()
    {
        $this->is_replaying = true;

        app(StateStore::class)->reset();

        app(EventStore::class)->read()
            ->each(function (VerbEvent $model) {
                // FIXME: This is currently applying events to the states before we're ready
                collect($model->event()->states())
                    ->each(fn ($state) => app(Dispatcher::class)->apply($model->event(), $state));

                return $model->event();
            });

        $this->is_replaying = false;
    }

    public function unlessReplaying(callable $callback)
    {
        if (! $this->is_replaying) {
            $callback();
        }
    }
}
