<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif
        }

        .level {
            margin-left: 2em;
        }
    </style>
</head>
<body>
<h1>@lang('External Courses Report')</h1>
<h2>@lang('Period :') {{ $period_start->locale(app()->getLocale())->isoFormat('Do MMMM YYYY') }} - {{ $period_end->locale(app()->getLocale())->isoFormat('Do MMM YYYY') }}</h2>
<h2>@lang('Institution :') {{ $data['partner_name'] }}</h2>

@foreach ($data['courses'] as $course)
    <h4>@lang('Course :') {{ $course['course_name'] }}</h4>
    <div class="level">
        <p>@lang('Classes completed :') {{ implode(' ; ', $course['events']) }}</p>
        <p>@lang('A total of :count hours at an hourly rate of $:price = $:total', ['count' => $course['hours_count'], 'price' => $course['hourly_price'], 'total' => $course['value']])</p>
    </div>
@endforeach
<p style="color: red;">@lang('Total for') {{ $data['partner_name'] }} : <strong>${{ $data['partner_balance'] }}</strong></p>

</body>
</html>
