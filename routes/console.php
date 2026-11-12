<?php

use Illuminate\Support\Facades\Schedule;

/*
 * These tasks run for each tenant, at a time of day in the institution timezone. The scheduler starts
 * them every 15 minutes, and each command only works for the tenants whose local time matches --at
 * (and day of the month matches --day). Commands that depend on a setting check it when they run.
 */
Schedule::command('campusflow:check-periods --at=00:00')->everyFifteenMinutes();
Schedule::command('campusflow:monthly-report --day=20 --at=00:00')->everyFifteenMinutes();
Schedule::command('campusflow:external-courses-report --at=03:15')->everyFifteenMinutes();
Schedule::command('campusflow:build-report --at=05:15')->everyFifteenMinutes();
Schedule::command('campusflow:attendance-reminders --at=08:15')->everyFifteenMinutes();

/*
 * Complaints past their time limit are escalated as soon as the next quarter of an hour starts, at any time of day.
 */
Schedule::command('campusflow:escalate-complaints')->everyFifteenMinutes();

Schedule::command('tenants:artisan activitylog:clean')->monthly();
