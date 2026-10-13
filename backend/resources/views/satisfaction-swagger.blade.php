<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>مستندات API رضایت‌مندی</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.17.14/swagger-ui.css">
    <style>
        body { margin: 0; font-family: sans-serif; background: #f8fafc; }
        header { padding: 16px 24px; background: #fff; border-bottom: 1px solid #e2e8f0; }
        header a { color: #2563eb; }
        #swagger-ui { direction: ltr; }
    </style>
</head>
<body>
<header><strong>API رضایت‌مندی</strong> · <a href="{{ url('/api/public/satisfaction/integration/openapi.json') }}">دانلود OpenAPI JSON</a></header>
<div id="swagger-ui"></div>
<script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.17.14/swagger-ui-bundle.js"></script>
<script>
    if (window.SwaggerUIBundle) {
        SwaggerUIBundle({
            url: @json(url('/api/public/satisfaction/integration/openapi.json')),
            dom_id: '#swagger-ui',
            deepLinking: true,
            presets: [SwaggerUIBundle.presets.apis],
            layout: 'BaseLayout'
        });
    } else {
        document.getElementById('swagger-ui').textContent = 'رابط Swagger بارگذاری نشد. فایل OpenAPI JSON از لینک بالا در دسترس است.';
    }
</script>
</body>
</html>
