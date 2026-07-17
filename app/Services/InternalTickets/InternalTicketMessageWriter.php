<?php

declare(strict_types=1);

namespace App\Services\InternalTickets;

use App\Models\InternalTicket;
use App\Models\InternalTicketAttachment;
use App\Models\InternalTicketMessage;
use App\Support\TicketHtmlSanitizer;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class InternalTicketMessageWriter
{
    private const MAX_ATTACHMENT_BYTES = 5 * 1024 * 1024;

    public function createMessage(
        InternalTicket $ticket,
        string $bodyHtml,
        int $senderAdminId,
        ?UploadedFile $attachment = null,
    ): InternalTicketMessage {
        $clean = TicketHtmlSanitizer::clean($bodyHtml);
        if (trim(strip_tags($clean)) === '') {
            throw ValidationException::withMessages(['body_html' => ['متن پیام الزامی است.']]);
        }

        $message = InternalTicketMessage::query()->create([
            'internal_ticket_id' => (int) $ticket->id,
            'body_html' => $clean,
            'body_excerpt' => TicketHtmlSanitizer::excerptFromHtml($clean),
            'sender_admin_id' => $senderAdminId,
        ]);

        if ($attachment !== null && $attachment->isValid()) {
            $this->storeAttachment($message, $attachment);
        }

        $ticket->update(['last_message_at' => Carbon::now()]);

        return $message->fresh(['attachments']);
    }

    public function storeAttachment(InternalTicketMessage $message, UploadedFile $file): void
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'zip'], true)) {
            throw ValidationException::withMessages([
                'attachment' => ['فرمت مجاز: jpg، png، pdf، doc، docx، zip'],
            ]);
        }

        $mime = (string) ($file->getMimeType() ?: 'application/octet-stream');
        $allowedMimes = [
            'image/jpeg',
            'image/png',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
            'application/x-zip-compressed',
        ];
        if (! in_array($mime, $allowedMimes, true)) {
            throw ValidationException::withMessages(['attachment' => ['نوع فایل مجاز نیست.']]);
        }

        if ($file->getSize() > self::MAX_ATTACHMENT_BYTES) {
            throw ValidationException::withMessages(['attachment' => ['حداکثر حجم فایل ۵ مگابایت است.']]);
        }

        $safeName = Str::lower(Str::random(20)).'.'.$ext;
        $dir = 'internal-tickets/'.(int) $message->internal_ticket_id;
        $path = $file->storeAs($dir, $safeName, 'local');

        InternalTicketAttachment::query()->create([
            'internal_ticket_message_id' => (int) $message->id,
            'storage_path' => $path,
            'original_filename' => mb_substr((string) $file->getClientOriginalName(), 0, 255),
            'mime_type' => mb_substr($mime, 0, 120),
            'file_size' => (int) $file->getSize(),
        ]);
    }

    public function replicateAttachments(InternalTicketMessage $source, InternalTicketMessage $target): void
    {
        $source->loadMissing('attachments');
        $disk = Storage::disk('local');

        foreach ($source->attachments as $attachment) {
            $fromPath = (string) $attachment->storage_path;
            if ($fromPath === '' || ! $disk->exists($fromPath)) {
                continue;
            }

            $ext = pathinfo($fromPath, PATHINFO_EXTENSION) ?: 'bin';
            $toPath = 'internal-tickets/'.(int) $target->internal_ticket_id.'/'.Str::lower(Str::random(20)).'.'.$ext;

            $disk->copy($fromPath, $toPath);

            InternalTicketAttachment::query()->create([
                'internal_ticket_message_id' => (int) $target->id,
                'storage_path' => $toPath,
                'original_filename' => (string) $attachment->original_filename,
                'mime_type' => (string) $attachment->mime_type,
                'file_size' => (int) $attachment->file_size,
            ]);
        }
    }
}
