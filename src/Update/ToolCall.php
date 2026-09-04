<?php

declare(strict_types=1);

namespace Develate\GrokCli\Update;

use Develate\GrokCli\Tool\Command;
use Develate\GrokCli\Tool\FileChange;
use Develate\GrokCli\Tool\ToolCallStatus;
use Develate\GrokCli\Value\FileChangeKind;

final readonly class ToolCall implements SessionUpdate
{
    private const COMMAND_NAMES = ['run_terminal_cmd', 'bash', 'shell'];

    private const FILE_NAMES = ['search_replace', 'write_file', 'edit_file', 'delete_file', 'move_file'];

    private const FILE_KINDS = ['edit', 'delete', 'move'];

    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $id,
        public string $title,
        public ?string $kind,
        public mixed $input,
        public ToolCallStatus $status,
        public ?string $toolName = null,
        public mixed $output = null,
        private array $raw = [],
    ) {
    }

    public function type(): string
    {
        return 'tool_call';
    }

    public function raw(): array
    {
        return $this->raw;
    }

    public function name(): string
    {
        if (is_string($this->toolName) && $this->toolName !== '') {
            return $this->toolName;
        }

        return $this->title;
    }

    public function isCommand(): bool
    {
        $name = strtolower($this->name());

        return $this->kind === 'execute' || in_array($name, self::COMMAND_NAMES, true);
    }

    public function asCommand(): ?Command
    {
        if (! $this->isCommand()) {
            return null;
        }

        $input = is_array($this->input) ? $this->input : [];
        $command = $input['command'] ?? $input['cmd'] ?? $this->title;
        $output = is_array($this->output) ? ($this->output['output'] ?? $this->output['stdout'] ?? null) : $this->output;
        $exit = is_array($this->output) ? ($this->output['exit_code'] ?? $this->output['exitCode'] ?? null) : null;

        return new Command(
            $this->id,
            is_string($command) ? $command : '',
            is_string($output) ? $output : (is_scalar($output) ? (string) $output : null),
            is_numeric($exit) ? (int) $exit : null,
        );
    }

    public function asFileChange(): ?FileChange
    {
        $name = strtolower($this->name());
        $kind = in_array($this->kind, self::FILE_KINDS, true)
            ? match ($this->kind) {
                'delete' => FileChangeKind::Delete,
                'move' => FileChangeKind::Update,
                default => FileChangeKind::Update,
            }
            : (in_array($name, self::FILE_NAMES, true) ? FileChangeKind::Update : null);

        if ($kind === null) {
            return null;
        }

        $input = is_array($this->input) ? $this->input : [];
        $path = $input['path'] ?? $input['file_path'] ?? $input['filePath'] ?? $input['target'] ?? null;
        if (! is_string($path) || $path === '') {
            return null;
        }

        if ($name === 'write_file' || $this->kind === 'create') {
            $kind = FileChangeKind::Add;
        }

        return new FileChange($this->id, $path, $kind);
    }

    /**
     * @param  array<string, mixed>  $update
     */
    public function withUpdate(array $update): self
    {
        $input = $update['rawInput'] ?? $update['input'] ?? $this->input;
        $output = $update['rawOutput'] ?? $update['output'] ?? $this->output;
        $status = array_key_exists('status', $update) ? ToolCallStatus::fromValue($update['status']) : $this->status;

        return new self(
            id: $this->id,
            title: is_string($update['title'] ?? null) ? $update['title'] : $this->title,
            kind: is_string($update['kind'] ?? null) ? $update['kind'] : $this->kind,
            input: $input,
            status: $status,
            toolName: is_string($update['toolName'] ?? null) ? $update['toolName'] : $this->toolName,
            output: $output,
            raw: $update + $this->raw,
        );
    }
}
