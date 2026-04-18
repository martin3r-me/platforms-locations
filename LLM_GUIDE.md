# Locations Module - LLM Guide

## Overview
- **Namespace**: `Platform\Locations`
- **Module Key**: `locations`
- **Service Provider**: `LocationsServiceProvider`
- **Config**: `config/locations.php`
- **Views**: `locations::livewire.*`
- **Livewire Prefix**: `locations.*`

## Architecture
- **ServiceProvider** registriert Config, Modul, Routes, Views, Livewire
- **Livewire Components** in `src/Livewire/`
- **Views** in `resources/views/livewire/`
- **Routes** in `routes/web.php`

## Important Patterns
- Team-based data: `$user->currentTeam`
- UUIDs for all models
- Layout: `->layout('platform::layouts.app')`
- Views: `locations::livewire.viewname`
