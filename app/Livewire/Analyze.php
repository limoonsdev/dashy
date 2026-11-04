<?php

namespace App\Livewire;

use App\Services\ProjectAnalyzer;
use Livewire\Component;
use Livewire\WithFileUploads;

class Analyze extends Component
{
    use WithFileUploads;

    public string $url = '';

    public $zip;

    /** @var array<string, mixed>|null */
    public ?array $result = null;

    public string $error = '';

    public function analyzeGithub(): void
    {
        $this->validate(['url' => ['required', 'string', 'max:500', 'regex:~github\.com/~i']]);
        $this->run(fn () => (new ProjectAnalyzer)->fromGithub($this->url));
    }

    public function analyzeZip(): void
    {
        $this->validate(['zip' => ['required', 'file', 'mimes:zip', 'max:51200']]);
        $this->run(fn () => (new ProjectAnalyzer)->fromZip($this->zip->getRealPath()));
    }

    public function resetAnalysis(): void
    {
        $this->result = $this->zip = null;
        $this->url = '';
        $this->error = '';
    }

    public function render()
    {
        return view('livewire.analyze');
    }

    private function run(callable $callback): void
    {
        $this->error = '';
        $this->result = null;

        try {
            $this->result = $callback();
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }
}
