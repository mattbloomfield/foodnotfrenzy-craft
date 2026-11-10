<?php

namespace MattBloomfield\RecipeHelper\console\commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;

/**
 * Shared plumbing for the recipe-helper commands, which were Yii console
 * controllers in Craft 5. Options keep their Yii names and forms:
 * `--dry-run`, `--dry-run=1` and `--dry-run=0` all work.
 */
abstract class RecipeHelperCommand extends Command
{
    public function __construct()
    {
        parent::__construct();
        $this->defineOptions();
    }

    abstract protected function defineOptions(): void;

    /**
     * A boolean option. Like Yii, it may be given with or without a value.
     */
    protected function flag(string $name, string $description): void
    {
        $this->getDefinition()->addOption(new InputOption($name, null, InputOption::VALUE_OPTIONAL, $description, false));
    }

    protected function valueOption(string $name, string $description, mixed $default = null): void
    {
        $this->getDefinition()->addOption(new InputOption($name, null, InputOption::VALUE_REQUIRED, $description, $default));
    }

    protected function flagValue(string $name): bool
    {
        $value = $this->option($name);

        // `--flag` with no value comes through as null.
        return $value === null || filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    protected function intOption(string $name): ?int
    {
        $value = $this->option($name);

        return $value === null || $value === '' ? null : (int)$value;
    }

    /**
     * Writes raw text (no trailing newline added), optionally in a color.
     */
    protected function stdout(string $text, ?string $color = null): void
    {
        $this->output->write($color ? "<fg=$color>" . $this->escape($text) . '</>' : $this->escape($text));
    }

    protected function stderr(string $text): void
    {
        $this->output->getErrorStyle()->write($this->escape($text));
    }

    private function escape(string $text): string
    {
        return \Symfony\Component\Console\Formatter\OutputFormatter::escape($text);
    }
}
