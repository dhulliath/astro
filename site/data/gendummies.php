<?php

$planets = array("Sun",
    "Moon",
    "Ascendant",
    "Mercury",
    "Venus",
    "Mars",
    "Jupiter",
    "Saturn",
    "Uranus",
    "Neptune",
    "Pluto",
    "Chiron",
    "AscendingNode",
    "Midheaven",
    "BlackMoonLilith");
$signs = array("Aries",
    "Aquarius",
    "Libra",
    "Gemini",
    "Capricorn",
    "Cancer",
    "Sagittarius",
    "Virgo",
    "Taurus",
    "Leo",
    "Pisces",
    "Scorpio");
$aspects = array("Conjunction",
    "Trine",
    "Sextile",
    "Square",
    "Opposition",
    "General");

foreach ($planets as $planet)
{
    foreach ($signs as $sign)
    {
        touch($planet . "_" . $sign . ".txt");
    }
    for ($i = 1; $i <= 12; $i++) {
        touch($planet . "_house" . $i . ".txt");
    }
    foreach ($planets as $planet2)
    {
        $comp = strcmp($planet, $planet2);
        if ($comp != 0)
        {
            foreach ($aspects as $aspect)
            {
                if (!file_exists("aspects/" . $aspect . "_" . $planet2 . "_" . $planet . ".txt"))
                {
                    touch("aspects/" . $aspect . "_" . $planet . "_" . $planet2 . ".txt");
                }
            }
        }
    }
}


?>