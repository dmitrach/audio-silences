<?php

namespace SegmentGenerator\App;

use SegmentGenerator\ChapterGenerators\ChapterGeneratorByAnalyzer;
use SegmentGenerator\ChapterSegmentators\ChapterSegmentator;
use SegmentGenerator\Contracts\GeneratorSettings;
use SegmentGenerator\Contracts\Logger;
use SegmentGenerator\Contracts\Outputter;
use SegmentGenerator\Contracts\SegmentGeneratorFacade;
use SegmentGenerator\Contracts\SilenceSegmentator;
use SegmentGenerator\Entities\Interval;
use SegmentGenerator\Entities\Silence;
use SegmentGenerator\Loggers\NullLogger;
use SegmentGenerator\Loggers\ScreenLogger;
use SegmentGenerator\Outputters\Decorators\ArrayBase;
use SegmentGenerator\Outputters\Decorators\ArrayWrapperDecorator;
use SegmentGenerator\Outputters\Decorators\JsonDecorator;
use SegmentGenerator\Outputters\FileOutputter;
use SegmentGenerator\Outputters\StdOutputter;
use SegmentGenerator\SilenceAnalyzers\SilenceAnalyzerByMinTransition;
use SegmentGenerator\SilenceSegmentators\SilenceSegmentatorByChapters;

class SettableFacade implements SegmentGeneratorFacade
{
    /**
     * Settings of the generator.
     *
     * @var GeneratorSettings
     */
    private $settings;

    /**
     * Read silences.
     *
     * @var Silence[]
     */
    private $silences;

    /**
     * A silence segmentator.
     *
     * @var SilenceSegmentator
     */
    private $segmentator;

    /**
     * An outputter.
     *
     * @var Outputter
     */
    private $outputter;

    /**
     * A logger.
     *
     * @var Logger
     */
    private $logger;

    public function __construct(GeneratorSettings $settings)
    {
        $this->settings = $settings;
        $this->logger = $settings->isDebug() ? new ScreenLogger : new NullLogger;
    }

    function getSilences(): iterable
    {
        if (!isset($this->silences)) {
            $this->silences = $this->newSilences();
        }

        return $this->silences;
    }

    /**
     * Reads and initializes silences.
     *
     * @return iterable
     */
    protected function newSilences(): iterable
    {
        $xml = simplexml_load_file($this->settings->getSource());
        $silences = [];

        foreach ($xml as $item) {
            $silences[] = new Silence(new Interval($item['from']), new Interval($item['until']));
        }

        return $silences;
    }

    public function getSegmentator(): SilenceSegmentator
    {
        if (!isset($this->segmentator)) {
            $this->segmentator = $this->newSegmentator();
        }

        return $this->segmentator;
    }

    /**
     * Initializes a new silence segmentator.
     *
     * @return SilenceSegmentator
     */
    protected function newSegmentator(): SilenceSegmentator
    {
        $analyzer = new SilenceAnalyzerByMinTransition($this->settings->getTransition());
        $chapterGenerator = new ChapterGeneratorByAnalyzer($analyzer, $this->logger);
        $chapterSegmentator = new ChapterSegmentator($this->settings->getMaxSegment(), $this->settings->getMinSilence());
        $silenceSegmentator = new SilenceSegmentatorByChapters($chapterGenerator, $chapterSegmentator, $this->logger);

        return $silenceSegmentator;
    }

    public function getOutputter(): Outputter
    {
        if (!isset($this->outputter)) {
            $this->outputter = $this->newOutputter();
        }

        return $this->outputter;
    }

    /**
     * Initializes a new outputter.
     *
     * @return Outputter
     */
    protected function newOutputter(): Outputter
    {
        $decorators = new JsonDecorator(new ArrayWrapperDecorator(new ArrayBase()));

        if ($this->settings->getOutput()) {
            return new FileOutputter($this->settings->getOutput(), $decorators);
        }

        return new StdOutputter($decorators);
    }
}