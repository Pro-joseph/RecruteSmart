<p>Bonjour,</p>

<p>
    Voici le récapitulatif des <strong>{{ $total }}</strong> nouvelle(s)
    candidature(s) reçues ces dernières 24 heures :
</p>

<ul>
    @foreach ($offers as $offer)
        <li>
            <a href="/app/offers/{{ $offer['offer_id'] }}">{{ $offer['offer_title'] }}</a>
            : {{ $offer['count'] }} candidature(s)
        </li>
    @endforeach
</ul>

<p>
    <a href="/app/offers">Voir toutes mes offres</a>
</p>

<p>L'équipe RecruteSmart</p>
