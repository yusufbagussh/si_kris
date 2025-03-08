<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{{ $tittle }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/icon_pharmacy_white.png') }}">
    {{--    @vite('resources/css/style.css')--}}
    <style>
        /* Define custom grid classes */
        .col-1-5 {
            flex: 0 0 12.5%;
            max-width: 12.5%;
        }

        /* Optional: Responsive styles */
        @media (min-width: 576px) {
            .col-md-1-5 {
                flex: 0 0 12.5%;
                max-width: 12.5%;
            }
        }

        /* Add more media queries as needed */
    </style>

</head>

<body>
{{-- @include('partials.navbar') --}}
<x-layouts.navbar :$active/>
@yield('container')
<x-layouts.footer/>
</body>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</html>
