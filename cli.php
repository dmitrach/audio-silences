<?php

require __DIR__ . '/vendor/autoload.php';

use SegmentGenerator\ChapterAnalyzers\ChapterAnalyzer;
use SegmentGenerator\ChapterGenerators\ChapterGeneratorByAnalyzer;
use SegmentGenerator\ChapterSegmenrators\ChapterSegmentator;
use SegmentGenerator\Entities\Interval;
use SegmentGenerator\Entities\Silence;
use SegmentGenerator\Loggers\NullLogger;
use SegmentGenerator\Loggers\ScreenLogger;
use SegmentGenerator\SilenceSegmentators\SilenceSegmentatorByChapters;

/**
 * The convention.
 *
 * The silence is a duration of an empty sound. They are taken from a XML file.
 * The chapter part is a duration with a sound that generated by silences. It has an offset from a last part. It can have side silences.
 * The chapter is a collection with chapter parts. It can have its own title.
 * The chapter collection is a complited book that contains chapters.
 * The segment is grouped parts of a chapter that has an offset from a last segment.
 */

// Expected arguments of CLI.
$shortopts = '';
$longopts = [];

// A file path to XML with silences.
$shortopts .= 's:';
$longopts[] = 'source:';

// A chapter transition. It is a silence duration which reliably indicates a chapter transition.
$shortopts .= 't:';
$longopts[] = 'transition:';

// A minimal silence between parts (segments) in a chapter which can be used to split a long chapter.
$shortopts .= 'm:';
$longopts[] = 'min-silence:';

// A duration of a segment in multiple segments after which the chapter will be broken up.
$shortopts .= 'd:';
$longopts[] = 'max-duration:';

// An output file with the result.
$shortopts .= 'o:';
$longopts[] = 'output:';

// A debug mode. It is used to show the processing.
$longopts[] = 'debug::';

$options = getopt($shortopts, $longopts);

// Gets arguments.
$path = $options['source'] ?? $options['s'] ?? null;
$transition = $options['transition'] ?? $options['t'] ?? null;
$minSilence = $options['min-silence'] ?? $options['m'] ?? null;
$maxDuration = $options['max-duration'] ?? $options['d'] ?? null;
$output = $options['output'] ?? $options['o'] ?? null;
$debug = isset($options['debug']) ? empty($options['debug']) : false;

if (empty($path)) {
    exit("The path to a file wasn't given. Set the path to a file through --source <path> or -s <path>.\n");
}

if (empty($transition)) {
    exit("The chapter transition wasn't given. Set the transition through --transition <duration> or -t <duration>. The duration should be greater than zero.\n");
} elseif (!is_numeric($transition)) {
    exit("The given transition isn't a number. The value should be an integer.\n");
}

if (!file_exists($path)) {
    exit("The $path file doesn't exist.\n");
}

$xml = simplexml_load_file($path);
/** @var Silence[] $silences */
$silences = [];

foreach ($xml as $item) {
    $silences[] = new Silence(new Interval($item['from']), new Interval($item['until']));
}
$logger = $debug ? new ScreenLogger : new NullLogger;
$analyzer = new ChapterAnalyzer($transition);
$chapterGenerator = new ChapterGeneratorByAnalyzer($logger, $analyzer);
$chapterSegmentator = new ChapterSegmentator($logger, $maxDuration, $minSilence);
$silenceSegmentator = new SilenceSegmentatorByChapters($logger, $chapterGenerator, $chapterSegmentator);
$segments = $silenceSegmentator->segment($silences);

$data = ['segments' => $segments->toArray()];

if (isset($output)) {
    file_put_contents($output, json_encode($data, JSON_PRETTY_PRINT));
} else {
    print(json_encode($data, JSON_PRETTY_PRINT)."\n");
}
