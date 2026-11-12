<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\ParentMessage;
use App\Models\PhoneNumber;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * WhatsApp messages to the parents of a student. The school sends them from its own WhatsApp: CampusFlow opens the
 * chat with the message written (a wa.me link) and keeps a copy of every message it prepared.
 */
class WhatsAppService
{
    /**
     * The phone numbers the student's parents can be reached on: the numbers of their contacts first, then the
     * student's own numbers. Numbers that cannot be used on WhatsApp are left out.
     *
     * @return array<string, string> the international number => who it belongs to, such as "Imran Khan (Father)"
     */
    public function recipientsFor(Student $student): array
    {
        $recipients = [];

        foreach ($student->contacts()->with(['phone', 'relationship'])->get() as $contact) {
            /** @var Contact $contact */
            $label = trim($contact->name).($contact->relationship ? ' ('.$contact->relationship->name.')' : '');

            foreach ($contact->phone as $phone) {
                $this->addRecipient($recipients, $phone, $label);
            }
        }

        foreach ($student->phone as $phone) {
            $this->addRecipient($recipients, $phone, $student->name.' ('.__('Student').')');
        }

        return $recipients;
    }

    /**
     * The number in international format without "+", as WhatsApp expects it: "0300-1234567" becomes
     * "923001234567". Returns null when the number is too short to be a mobile number.
     */
    public function normalize(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        $countryCode = (string) config('campusflow.whatsapp_country_code');

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $digits = $countryCode.substr($digits, 1);
        } elseif (strlen($digits) === 10 && ! str_starts_with($digits, $countryCode)) {
            $digits = $countryCode.$digits;
        }

        return strlen($digits) >= 11 && strlen($digits) <= 15 ? $digits : null;
    }

    /**
     * The link that opens a WhatsApp chat with the number, with the message already written.
     */
    public function link(string $phone, string $body): string
    {
        return 'https://wa.me/'.$this->normalize($phone).'?text='.rawurlencode($body);
    }

    /**
     * Keeps a copy of a message prepared for the parents of the student, with what it was about.
     */
    public function record(Student $student, string $phone, string $body, ?string $recipientName = null, ?Model $about = null, ?User $sentBy = null): ParentMessage
    {
        return ParentMessage::query()->create([
            'student_id' => $student->id,
            'campus_id' => $about?->getAttribute('campus_id') ?? $student->latestAdmission?->campus_id,
            'channel' => 'whatsapp',
            'recipient_name' => $recipientName,
            'phone' => $this->normalize($phone) ?? $phone,
            'body' => $body,
            'about_type' => $about?->getMorphClass(),
            'about_id' => $about?->getKey(),
            'sent_by' => $sentBy?->id,
        ]);
    }

    /**
     * @param  array<string, string>  $recipients
     */
    private function addRecipient(array &$recipients, PhoneNumber $phone, string $label): void
    {
        $number = $this->normalize($phone->phone_number);

        if ($number !== null && ! isset($recipients[$number])) {
            $recipients[$number] = $label.' · +'.$number;
        }
    }
}
