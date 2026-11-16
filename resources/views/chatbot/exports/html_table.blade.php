<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Hasil Query — AI Assistant</title>
    <style>
        body { font-family: "Segoe UI", Arial, sans-serif; font-size: 13px; color: #101828; margin: 24px; }
        h1 { font-size: 18px; color: #1d3f86; margin: 0 0 4px; }
        .meta { font-size: 12px; color: #475467; margin-bottom: 14px; }
        table { border-collapse: collapse; width: 100%; }
        th { background: #1d3f86; color: #ffffff; text-align: left; padding: 8px 10px; font-size: 12px; }
        td { border-bottom: 1px solid #e4e7ec; padding: 7px 10px; font-size: 12px; }
        tr:nth-child(even) td { background: #f8fafc; }
        td.kosong { color: #475467; font-style: italic; }
    </style>
</head>
<body>
    <h1>Hasil Query — AI Assistant</h1>
    <div class="meta">Chutex HRIS · {{ count($rows) }} baris · {{ now()->format('d/m/Y H:i') }} WIB</div>
    @include('chatbot.exports._table', ['rows' => $rows, 'headers' => $headers])
</body>
</html>
