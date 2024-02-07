<table>
    <thead>
    <tr>
        <th>Call ID</th>
        <th>Direction</th>
        <th>Caller ID Name</th>
        <th>Caller ID Number</th>
        <th>Dialed Number</th>
        <th>Recipient</th>
        <th>Date</th>
        <th>Time</th>
        <th>Duration</th>
        <th>Status</th>
    </tr>
    </thead>
    <tbody>
    @foreach($cdrs as $cdr)
        <tr>
            <td>{{ $cdr->xml_cdr_uuid }}</td>
            <td>{{ $cdr->direction }}</td>
            <td>{{ $cdr->caller_id_name }}</td>
            <td>{{ $cdr->caller_id_number }}</td>
            <td>{{ $cdr->caller_destination }}</td>
            <td>{{ $cdr->destination_number }}</td>
            <td>{{ $cdr->start_date }}</td>
            <td>{{ $cdr->start_time }}</td>
            <td>{{ $cdr->duration }}</td>
            <td>{{ $cdr->status }}</td>

            
        </tr>
    @endforeach
    </tbody>
</table>