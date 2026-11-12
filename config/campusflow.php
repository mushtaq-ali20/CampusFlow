<?php

/*
 * Institution settings. These are defaults: the values saved in the Settings area
 * override them (see App\Services\Settings).
 */
return [
    'company_name' => 'CampusFlow',
    'company_address' => null,
    'company_phone' => null,
    'company_id' => null,
    'company_email' => null,

    /*
     * Default language, for visitors, emails and documents. Each user can choose their own.
     * It is kept apart from app.locale, which App::setLocale() changes to the current user's language.
     */
    'locale' => 'en',

    /*
     * Path of the institution logo on the public disk.
     */
    'logo' => null,

    'calendar_start' => '06:00:00',

    'currency_symbol' => 'Rs',
    'currency_code' => 'PKR',
    'currency_position' => 'before',

    /*
     * Country calling code added to local phone numbers (such as 0300-1234567) for WhatsApp messages to parents.
     */
    'whatsapp_country_code' => '92',

    /*
     * The weekly days off of the school as day-of-week numbers (0 = Sunday, 6 = Saturday). They are not counted as
     * days of leave.
     */
    'weekly_off_days' => [0],

    'send_emails_for_absences' => false,

    'send_attendance_reminders' => false,

    'send_emails_for_results' => false,

    'include_takings_in_reports' => false,
];
