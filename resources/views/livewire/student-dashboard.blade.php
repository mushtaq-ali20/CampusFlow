<div>
    <h1 class="mb-6 text-2xl font-bold">{{ __('Dashboard') }}</h1>

    @if ($enrollments->isEmpty())
        <div class="rounded-lg border border-gray-200 bg-white p-8 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
            </svg>
            <h3 class="mt-4 text-lg font-medium text-gray-900">{{ __('No enrollments') }}</h3>
            <p class="mt-1 text-sm text-gray-500">{{ __('You are not enrolled in any course at the moment.') }}</p>
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($enrollments as $enrollment)
                <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-200 bg-gray-50 px-4 py-3">
                        <h3 class="font-semibold text-gray-800">{{ $enrollment->course->name }}</h3>
                    </div>
                    <div class="space-y-2 px-4 py-4 text-sm">
                        <p>
                            <span class="text-gray-500">{{ __('Period') }}:</span>
                            {{ $enrollment->course->period->name }}
                        </p>

                        @if ($enrollment->result && $enrollment->status_id !== 1)
                            <p>
                                <span class="text-gray-500">{{ __('Result') }}:</span>
                                <span class="font-medium">{{ $enrollment->result->result_name->name ?? '-' }}</span>
                            </p>
                        @elseif ($enrollment->status_id === 1)
                            <p class="text-amber-600">{{ __('The enrollment is unpaid') }}</p>
                        @endif

                        <p>
                            <span class="text-gray-500">{{ __('Status') }}:</span>
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium
                                {{ $enrollment->status_id === 2 ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                                {{ $enrollment->enrollmentStatus->name ?? '-' }}
                            </span>
                        </p>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
