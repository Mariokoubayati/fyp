import { router } from 'expo-router';

import { createEvent } from '@/api/events';
import { EventForm } from '@/components/EventForm';

export default function NewEvent() {
  return (
    <EventForm
      submitLabel="Post event"
      onSubmit={async (payload) => {
        const event = await createEvent(payload);
        router.replace(`/event/${event.id}`);
      }}
    />
  );
}
