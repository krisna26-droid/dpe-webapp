<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard - Dharma Private English</title>
</head>
<body>

    <h1>Dashboard DPE</h1>

    <p>Selamat datang, {{ $user->full_name }}</p>

    <p>Username: {{ $user->username }}</p>

    <p>Role: {{ $user->role_code }}</p>

    <form method="POST" action="{{ route('logout') }}">
        @csrf

        <button type="submit">
            Logout
        </button>
    </form>

</body>
</html>