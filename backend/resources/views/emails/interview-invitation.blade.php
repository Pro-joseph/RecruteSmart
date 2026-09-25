<p>Bonjour {{ $name }},</p>

<p>
    Vous êtes invité(e) à un entretien pour l'offre
    <strong>{{ $offerTitle }}</strong>.
</p>

<ul>
    <li>Date : {{ $startsAt }} (durée {{ $duration }} minutes)</li>
    <li>Format : {{ $type === 'phone' ? 'téléphone' : ($type === 'video' ? 'visioconférence' : 'sur site') }}</li>
    @if ($location)
        <li>Lieu ou lien : {{ $location }}</li>
    @endif
</ul>

<p>L'invitation calendrier (.ics) est jointe à cet email.</p>

<p>À bientôt,</p>
