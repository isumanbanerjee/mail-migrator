<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= isset($title) ? $e($title) : 'Email Migration' ?></title>
  <link rel="stylesheet" href="/assets/app.css">
  <script defer src="/assets/alpine.min.js"></script>
</head>
<body>
  <main><?= $content ?></main>
</body>
</html>
