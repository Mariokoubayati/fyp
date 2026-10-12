/** "Tue, Oct 13 · 4:30 PM" in the device's locale. */
export function formatDateTime(iso: string): string {
  const date = new Date(iso);

  const day = date.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' });
  const time = date.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });

  return `${day} · ${time}`;
}
