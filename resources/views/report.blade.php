<!DOCTYPE html>
<html>
<head>
    <title>Report</title>
</head>
<body>
    <h1>Time Report Summary</h1>

    <h2>By Date</h2>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Total Hours</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($report['by_date'] as $row)
                <tr>
                    <td>{{ $row['date'] }}</td>
                    <td>{{ $row['total_hours'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>By Project</h2>
    <table>
        <thead>
            <tr>
                <th>Project ID</th>
                <th>Hours</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($report['by_project'] as $row)
                <tr>
                    <td>{{ $row['project_id'] }}</td>
                    <td>{{ $row['hours'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>By Client</h2>
    <table>
        <thead>
            <tr>
                <th>Client ID</th>
                <th>Hours</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($report['by_client'] as $row)
                <tr>
                    <td>{{ $row['client_id'] }}</td>
                    <td>{{ $row['hours'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
