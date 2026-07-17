<?php

declare(strict_types=1);

namespace App\Services\Customers;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\CustomerAdminNote;
use Carbon\Carbon;
use Hekmatinasser\Jalali\Jalali;
use Illuminate\Validation\ValidationException;

final class CustomerAdminNoteService
{
    private const MAX_BODY_LENGTH = 5000;

    /**
     * @return list<array<string, mixed>>
     */
    public function listForCustomer(Customer $customer, Admin $viewer): array
    {
        $notes = CustomerAdminNote::query()
            ->where('customer_id', (int) $customer->id)
            ->with('admin')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return $notes->map(fn (CustomerAdminNote $note): array => $this->mapNote($note, $viewer))->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function create(Customer $customer, Admin $admin, string $body): array
    {
        $clean = $this->normalizeBody($body);

        $note = CustomerAdminNote::query()->create([
            'customer_id' => (int) $customer->id,
            'admin_id' => (int) $admin->id,
            'body' => $clean,
        ]);

        $note->load('admin');

        return $this->mapNote($note, $admin);
    }

    /**
     * @return array<string, mixed>
     */
    public function update(CustomerAdminNote $note, Admin $admin, string $body): array
    {
        $this->assertAuthor($note, $admin);
        $clean = $this->normalizeBody($body);

        $note->update(['body' => $clean]);
        $note->load('admin');

        return $this->mapNote($note->fresh(['admin']), $admin);
    }

    public function delete(CustomerAdminNote $note, Admin $admin): void
    {
        $this->assertAuthor($note, $admin);
        $note->delete();
    }

    public function assertNoteBelongsToCustomer(CustomerAdminNote $note, Customer $customer): void
    {
        if ((int) $note->customer_id !== (int) $customer->id) {
            abort(404);
        }
    }

    private function assertAuthor(CustomerAdminNote $note, Admin $admin): void
    {
        if ((int) $note->admin_id !== (int) $admin->id) {
            throw ValidationException::withMessages([
                'note' => ['فقط نویسنده می‌تواند این یادداشت را ویرایش یا حذف کند.'],
            ]);
        }
    }

    private function normalizeBody(string $body): string
    {
        $clean = trim(preg_replace("/\r\n?/", "\n", $body) ?? $body);
        if ($clean === '') {
            throw ValidationException::withMessages([
                'body' => ['متن یادداشت الزامی است.'],
            ]);
        }
        if (mb_strlen($clean) > self::MAX_BODY_LENGTH) {
            throw ValidationException::withMessages([
                'body' => ['حداکثر طول یادداشت '.self::MAX_BODY_LENGTH.' نویسه است.'],
            ]);
        }

        return $clean;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapNote(CustomerAdminNote $note, Admin $viewer): array
    {
        $author = $note->admin;
        $authorName = $author !== null ? $author->fullName() : 'ادمین';
        if (trim($authorName) === '') {
            $authorName = 'ادمین #'.(int) $note->admin_id;
        }

        $createdAt = Carbon::parse($note->created_at);
        $updatedAt = Carbon::parse($note->updated_at);
        $wasEdited = $updatedAt->gt($createdAt->copy()->addSeconds(2));

        return [
            'id' => (int) $note->id,
            'body' => (string) $note->body,
            'author_name' => $authorName,
            'is_mine' => (int) $note->admin_id === (int) $viewer->id,
            'created_at_fa' => $this->formatDateTimeFa($createdAt),
            'updated_at_fa' => $this->formatDateTimeFa($updatedAt),
            'was_edited' => $wasEdited,
        ];
    }

    private function formatDateTimeFa(Carbon $c): string
    {
        $jDate = Jalali::enToFaNumbers(Jalali::instance($c)->format('Y/m/d'));
        $time = Jalali::enToFaNumbers($c->format('H:i'));

        return $jDate.'، '.$time;
    }
}
