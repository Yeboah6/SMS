<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Shared behaviour for the two small lists staff are filed under: departments and designations.
 * A record that staff are still assigned to is never deleted, so nobody is silently left without one.
 */
abstract class StaffLookupController extends Controller
{
    /** @return class-string<Model> */
    abstract protected function model(): string;

    /** Permission that guards every action, for example departments.manage. */
    abstract protected function permission(): string;

    /** The staff column that points at this list, for example department_id. */
    abstract protected function column(): string;

    /** The other list, shown beside each staff member (department shows designation and vice versa). */
    abstract protected function otherRelation(): string;

    /** Inertia folder and URL segment, for example Departments / departments. */
    abstract protected function folder(): string;

    abstract protected function url(): string;

    /** Singular and plural wording for messages. */
    abstract protected function noun(): string;

    protected function renderIndex(Request $request): Response
    {
        abort_unless($request->user()->can($this->permission()), 403);

        $counts = Staff::query()
            ->whereNotNull($this->column())
            ->select($this->column(), DB::raw('count(*) as total'))
            ->groupBy($this->column())
            ->pluck('total', $this->column());

        return Inertia::render($this->folder().'/Index', [
            'items' => $this->model()::query()
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (Model $item): array => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'staffCount' => (int) ($counts[$item->id] ?? 0),
                ]),
            'unassigned' => Staff::query()->whereNull($this->column())->count(),
        ]);
    }

    protected function renderShow(Request $request, Model $item): Response
    {
        abort_unless($request->user()->can($this->permission()), 403);

        return Inertia::render($this->folder().'/Show', [
            'item' => ['id' => $item->id, 'name' => $item->name],
            'staff' => Staff::query()
                ->with($this->otherRelation().':id,name')
                ->where($this->column(), $item->id)
                ->orderBy('name')
                ->orderBy('id')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (Staff $member): array => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'staffNumber' => $member->staff_number,
                    'status' => $member->status,
                    'isTeacher' => (bool) $member->is_teacher,
                    'other' => $member->{$this->otherRelation()}?->name,
                ]),
        ]);
    }

    protected function save(array $data, ?Model $item = null): RedirectResponse
    {
        if ($item) {
            $item->update(['name' => trim($data['name'])]);
            $message = ucfirst($this->noun()).' renamed.';
        } else {
            $this->model()::query()->create(['name' => trim($data['name'])]);
            $message = ucfirst($this->noun()).' added.';
        }

        return to_route($this->url().'.index')->with('status', $message);
    }

    protected function remove(Request $request, Model $item): RedirectResponse
    {
        abort_unless($request->user()->can($this->permission()), 403);

        $assigned = Staff::query()->where($this->column(), $item->id)->count();
        if ($assigned > 0) {
            throw ValidationException::withMessages([
                $this->noun() => "{$assigned} staff member".($assigned === 1 ? ' is' : 's are')." assigned to this {$this->noun()}. Move them to another {$this->noun()} first.",
            ]);
        }

        $item->delete();

        return to_route($this->url().'.index')->with('status', ucfirst($this->noun()).' deleted.');
    }
}
