<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Hasil Query — AI Assistant</title>
    <style>
        * { font-family: "DejaVu Sans", sans-serif; }
        body { font-size: 10px; color: #101828; margin: 0; }
        h1 { font-size: 14px; color: #1d3f86; margin: 0 0 2px; }
        .meta { font-size: 9px; color: #475467; margin-bottom: 10px; }
        table { border-collapse: collapse; width: 100%; }
        th { background: #1d3f86; color: #ffffff; text-align: left; padding: 5px 6px; font-size: 9px; }
        td { border-bottom: 1px solid #e4e7ec; padding: 4px 6px; font-size: 9px; }
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
