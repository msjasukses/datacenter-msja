<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Kelola akun pengguna panel admin (tabel users).
 * Hanya Super Admin yang boleh membuat/mengubah akun ber-role super-admin.
 */
class PenggunaController extends Controller
{
    public const STATUS = [
        'active'    => 'Aktif',
        'inactive'  => 'Non-aktif',
        'suspended' => 'Ditangguhkan',
    ];

    public function index(Request $request)
    {
        $q = $request->input('q');
        $items = User::with('peran')
            ->when($q, fn ($x) => $x->where(fn ($w) => $w->where('name', 'like', "%$q%")->orWhere('email', 'like', "%$q%")))
            ->orderBy('name')
            ->paginate(15)->withQueryString();

        return view('pengguna.index', compact('items', 'q'));
    }

    public function create()
    {
        return view('pengguna.form', ['item' => new User(['account_status' => 'active']), 'roles' => $this->roleOptions()]);
    }

    public function store(Request $r)
    {
        $data = $this->v($r);
        User::create($data + ['role' => 'admin', 'is_aktif' => $data['account_status'] === 'active']);

        return redirect()->route('pengguna.index')->with('success', 'Akun pengguna ditambahkan.');
    }

    public function edit(User $pengguna)
    {
        $this->guardSuperAdmin($pengguna);

        return view('pengguna.form', ['item' => $pengguna, 'roles' => $this->roleOptions()]);
    }

    public function update(Request $r, User $pengguna)
    {
        $this->guardSuperAdmin($pengguna);
        $data = $this->v($r, $pengguna);

        if ($pengguna->is(auth()->user())) {
            // Cegah admin mengunci/menurunkan akunnya sendiri.
            $data['account_status'] = 'active';
            $data['role_id'] = $pengguna->role_id;
        }
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }
        $data['is_aktif'] = $data['account_status'] === 'active';
        if ($data['account_status'] === 'active') {
            $data['failed_login_count'] = 0;
            $data['locked_until'] = null;
        }

        $pengguna->update($data);

        return redirect()->route('pengguna.index')->with('success', 'Akun pengguna diperbarui.');
    }

    public function destroy(User $pengguna)
    {
        $this->guardSuperAdmin($pengguna);
        if ($pengguna->is(auth()->user())) {
            return back()->with('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }

        $pengguna->delete();

        return back()->with('success', 'Akun pengguna dihapus.');
    }

    protected function v(Request $r, ?User $user = null): array
    {
        return $r->validate([
            'name'           => 'required|string|max:255',
            'email'          => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'nomor_hp'       => 'nullable|string|max:20',
            'role_id'        => ['required', Rule::in(array_keys($this->roleOptions()))],
            'account_status' => ['required', Rule::in(array_keys(self::STATUS))],
            'password'       => [$user ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
        ], [], [
            'name' => 'nama', 'nomor_hp' => 'nomor HP', 'role_id' => 'peran', 'account_status' => 'status akun',
        ]);
    }

    /** Role yang boleh dipilih oleh user yang sedang login. */
    protected function roleOptions(): array
    {
        return Role::orderBy('id')
            ->when(! $this->isSuperAdmin(), fn ($q) => $q->where('name', '!=', 'super-admin'))
            ->pluck('label', 'id')->all();
    }

    protected function isSuperAdmin(): bool
    {
        return auth()->user()?->peran?->name === 'super-admin';
    }

    protected function guardSuperAdmin(User $target): void
    {
        if ($target->peran?->name === 'super-admin' && ! $this->isSuperAdmin()) {
            abort(403, 'Hanya Super Administrator yang dapat mengelola akun ini.');
        }
    }
}
