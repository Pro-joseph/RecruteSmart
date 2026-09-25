<p>Bonjour {{ $name }},</p>

<p>
    Nous vous remercions pour l'intérêt que vous portez à l'offre
    <strong>{{ $offerTitle }}</strong>.
</p>

<p>
    Après étude de votre candidature, nous avons décidé de ne pas donner
    suite à ce stade.
</p>

@if ($extraMessage)
    <p>{{ $extraMessage }}</p>
@endif

<p>Nous vous souhaitons beaucoup de succès dans vos recherches.</p>
