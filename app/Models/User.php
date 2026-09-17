<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use RuntimeException;

/**
 * حساب داخل المنظومة المغلقة (S13).
 *
 * لا يُنشئ أحد حسابه بنفسه: المدير وحده ينشئ حسابات المعلّمين ويسلّمهم اسم
 * المستخدم وكلمة المرور يدويًا. لا بريد إلكتروني في الجدول أصلًا — لا كمعرّف
 * دخول ولا كقناة استعادة (انظر هجرة close_user_accounts_system).
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_TEACHER = 'teacher';

    public const ROLES = [
        self::ROLE_ADMIN   => 'مدير',
        self::ROLE_TEACHER => 'معلّم',
    ];

    protected $fillable = [
        'name',
        'username',
        'role',
        'is_active',
        'password',
        'mosque',
        'classroom',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_active'     => 'boolean',
        'last_login_at' => 'datetime',
    ];

    protected $attributes = [
        'role'      => self::ROLE_TEACHER,
        'is_active' => true,
    ];

    /**
     * حارس آخر مدير.
     *
     * الطلب الأصلي كان "حساب مدير واحد لا يُحذف". تجميد صفّ بعينه في القاعدة
     * يحقّق ذلك لكنه يمنع أيضًا استبدال بيانات اعتماد المدير يومًا ما (تسريب،
     * تسليم المهمة لشخص آخر). القاعدة المطبَّقة هنا تعطي نفس الضمان بمرونة
     * أوسع: لا يبقى النظام بلا مدير نشط أبدًا — لا حذف آخر مدير، ولا تحويله
     * إلى معلّم، ولا تعطيله.
     *
     * الحارس في الموديل لا في المتحكّم: أي مسار حذف مستقبلي (أمر artisan، شاشة
     * جديدة، tinker) يمرّ به تلقائيًا، لا فقط الشاشة المكتوبة اليوم.
     */
    protected static function booted()
    {
        static::deleting(function (User $user) {
            if ($user->isAdmin() && static::otherActiveAdminsExist($user) === false) {
                throw new RuntimeException('لا يمكن حذف آخر حساب مدير في النظام.');
            }
        });

        static::updating(function (User $user) {
            $wasAdmin = $user->getOriginal('role') === self::ROLE_ADMIN;

            if (! $wasAdmin) {
                return;
            }

            $losesAdmin = $user->role !== self::ROLE_ADMIN;
            $losesAccess = $user->is_active === false;

            if (($losesAdmin || $losesAccess) && static::otherActiveAdminsExist($user) === false) {
                throw new RuntimeException('لا يمكن تعطيل آخر حساب مدير أو تغيير دوره.');
            }
        });
    }

    protected static function otherActiveAdminsExist(User $user): bool
    {
        return static::query()
            ->where('role', self::ROLE_ADMIN)
            ->where('is_active', true)
            ->whereKeyNot($user->getKey())
            ->exists();
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isTeacher(): bool
    {
        return $this->role === self::ROLE_TEACHER;
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }

    public function scopeTeachers(Builder $query): Builder
    {
        return $query->where('role', self::ROLE_TEACHER);
    }

    public function scopeAdmins(Builder $query): Builder
    {
        return $query->where('role', self::ROLE_ADMIN);
    }

    /**
     * علاقة المعلم بطلابه
     * Teacher 1 -----> Many Students
     *
     * withoutGlobalScopes: TeacherScope يقيّد كل استعلام على طلاب المستخدم
     * المسجَّل دخوله — وهو الصحيح في شاشات المعلّم، لكنه هنا يُفرغ عدّ طلاب
     * معلّم آخر في لوحة المدير. العلاقة تُعرَّف على المالك الحقيقي للصفوف.
     */
    public function students()
    {
        return $this->hasMany(Student::class, 'teacher_id', 'id')->withoutGlobalScopes();
    }
}
