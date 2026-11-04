<?php

namespace App\Livewire\Tools;

use App\Services\Toolkit\ToolCatalog;
use Livewire\Component;

class Index extends Component
{
    public string $category = 'All';

    public function setCategory(string $category): void
    {
        $this->category = $category;
    }

    public function render()
    {
        $tools = collect(ToolCatalog::all());
        $categories = ['All', ...$tools->pluck('category')->unique()->sort()->values()->all()];

        return view('livewire.tools.index', [
            'categories' => $categories,
            'tools' => $this->category === 'All' ? $tools : $tools->where('category', $this->category),
        ]);
    }
}
