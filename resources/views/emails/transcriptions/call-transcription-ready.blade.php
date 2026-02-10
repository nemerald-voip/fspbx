<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        /* Email Client Reset & Base Styles */
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; line-height: 1.6; color: #333333; margin: 0; padding: 0; background-color: #f4f4f7; }
        .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; }
        
        /* Header */
        .header { background-color: #2d3748; color: #ffffff; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 20px; font-weight: 600; }
        .header-meta { margin-top: 10px; font-size: 14px; opacity: 0.8; }
        
        /* Badges */
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }
        .badge-positive { background-color: #c6f6d5; color: #22543d; }
        .badge-negative { background-color: #fed7d7; color: #822727; }
        .badge-neutral { background-color: #edf2f7; color: #4a5568; }

        /* Summary Section */
        .section { padding: 25px 20px; border-bottom: 1px solid #edf2f7; }
        .section-title { font-size: 14px; font-weight: bold; text-transform: uppercase; color: #718096; margin-bottom: 10px; letter-spacing: 1px; }
        .summary-box { background-color: #ebf8ff; border-left: 4px solid #4299e1; padding: 15px; border-radius: 4px; font-style: italic; color: #2c5282; }
        
        /* Action Items */
        .action-box { background-color: #fffaf0; border: 1px solid #fbd38d; border-radius: 6px; padding: 15px; }
        .action-item { display: flex; align-items: flex-start; margin-bottom: 8px; }
        .checkbox { min-width: 16px; margin-right: 10px; font-size: 16px; }
        
        /* Script / Transcript */
        .script-container { padding: 20px; background-color: #fcfcfc; }
        .script-line { margin-bottom: 15px; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; position: relative; }
        
        /* Agent Styling (Blue tint) */
        .is-agent { background-color: #f0f7ff; border-color: #bee3f8; margin-left: 20px; }
        /* Customer Styling (White/Grey) */
        .is-customer { background-color: #ffffff; margin-right: 20px; }
        
        .speaker-name { font-size: 12px; font-weight: bold; color: #4a5568; margin-bottom: 4px; display: block; }
        .timestamp { float: right; font-weight: normal; color: #a0aec0; font-size: 11px; }
        .text { font-size: 14px; color: #2d3748; }

        /* Button */
        .btn-container { text-align: center; padding: 30px 20px; background-color: #f4f4f7; }
        .btn { background-color: #48bb78; color: #ffffff; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block; }
    </style>
</head>
<body>

<div class="container">
    <!-- Header -->
    <div class="header">
        <h1>Call Transcription Report</h1>
        <div class="header-meta">
            {{ $data['date'] }} &bull; Duration: {{ $data['duration'] }}
        </div>
        <div style="margin-top: 15px;">
            @php
                $badgeClass = match(strtolower($data['sentiment'])) {
                    'positive' => 'badge-positive',
                    'negative' => 'badge-negative',
                    default => 'badge-neutral',
                };
            @endphp
            <span class="badge {{ $badgeClass }}">
                Sentiment: {{ $data['sentiment'] }}
            </span>
        </div>
    </div>

    <!-- Executive Summary -->
    <div class="section">
        <div class="section-title">Executive Summary</div>
        <div class="summary-box">
            "{{ $data['summary'] }}"
        </div>
    </div>

    <!-- Action Items -->
    @if(!empty($data['action_items']))
    <div class="section">
        <div class="section-title">Action Items & Next Steps</div>
        <div class="action-box">
            @foreach($data['action_items'] as $item)
                <div class="action-item">
                    <span class="checkbox">☐</span> 
                    <span>
                        @if($item['owner'])
                            <strong>[{{ $item['owner'] }}]</strong>
                        @endif
                        {{ $item['description'] }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Transcript Script -->
    <div class="script-container">
        <div class="section-title" style="margin-bottom: 20px; text-align: center;">Full Conversation Log</div>

        @foreach($data['utterances'] as $line)
            @php
                // Determine Speaker Name from Map
                $speakerLabel = $line['speaker'];
                $speakerName = $data['speaker_map'][$speakerLabel] ?? "Speaker $speakerLabel";
                
                // Check if this is the Agent (Matches the 'agent_label' determined in Job)
                $isAgent = ($speakerLabel === $data['agent_label']);
                $rowClass = $isAgent ? 'is-agent' : 'is-customer';
                
                // Format Timestamp (ms to mm:ss)
                $time = gmdate("i:s", intval($line['start'] / 1000));
            @endphp

            <div class="script-line {{ $rowClass }}">
                <span class="speaker-name">
                    {{ $speakerName }}
                    <span class="timestamp">{{ $time }}</span>
                </span>
                <div class="text">
                    {{ $line['text'] }}
                </div>
            </div>
        @endforeach
    </div>

</div>

</body>
</html>