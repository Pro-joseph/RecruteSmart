<p>Bonjour,</p>

@if ($messageBody)
    <p>{!! nl2br(e($messageBody)) !!}</p>
@else
    <p>{{ $senderName }} vous transmet les candidatures suivantes :</p>
@endif

<ul>
    @foreach ($candidates as $candidate)
        <li>
            <strong>{{ $candidate['name'] }}</strong>
            @if ($candidate['link'])
                — <a href="{{ $candidate['link'] }}">Télécharger le CV (lien valable 7 jours)</a>
            @endif
            @if ($candidate['analysis'])
                <br />
                Score de correspondance : {{ $candidate['analysis']['match_score'] }} %
                @if ($candidate['analysis']['summary'])
                    — {{ $candidate['analysis']['summary'] }}
                @endif
            @endif
        </li>
    @endforeach
</ul>

@if ($deliveryNote)
    <p><em>{{ $deliveryNote }}</em></p>
@endif

<p>— {{ $senderName }}</p>
