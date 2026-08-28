<?php

$file = __DIR__ . "/../data/merken.json";

$logos = json_decode(file_get_contents($file), true);

if (!is_array($logos)) {
    $logos = [];
}

?>

<section class="merken">

    <div class="logo-slider">

        <div class="logo-track">

            <?php for ($i = 0; $i < 2; $i++): ?>

                <?php foreach ($logos as $logo): ?>

                    <img
                        src="/VEA/afbeeldingen/<?= htmlspecialchars($logo["bestand"]) ?>"
                        alt="<?= htmlspecialchars($logo["naam"]) ?>">

                <?php endforeach; ?>

            <?php endfor; ?>

        </div>

    </div>

</section>