warning: in the working copy of '03-execute/23-week/plan-mejoramiento/api/bootstrap/app.php', CRLF will be replaced by LF the next time Git touches it
[1mdiff --git a/03-execute/23-week/plan-mejoramiento/api/app/Models/Beneficiary.php b/03-execute/23-week/plan-mejoramiento/api/app/Models/Beneficiary.php[m
[1mindex a5972b3..d33a330 100644[m
[1m--- a/03-execute/23-week/plan-mejoramiento/api/app/Models/Beneficiary.php[m
[1m+++ b/03-execute/23-week/plan-mejoramiento/api/app/Models/Beneficiary.php[m
[36m@@ -4,9 +4,13 @@[m
 [m
 use Illuminate\Database\Eloquent\Attributes\Fillable;[m
 use Illuminate\Database\Eloquent\Model;[m
[32m+[m[32muse Illuminate\Database\Eloquent\Relations\HasMany;[m
 [m
 #[Fillable(['full_name', 'identification_number'])][m
 class Beneficiary extends Model[m
 {[m
[31m-    //[m
[32m+[m[32m    public function loans(): HasMany[m
[32m+[m[32m    {[m
[32m+[m[32m        return $this->hasMany(Loan::class);[m
[32m+[m[32m    }[m
 }[m
[1mdiff --git a/03-execute/23-week/plan-mejoramiento/api/app/Models/User.php b/03-execute/23-week/plan-mejoramiento/api/app/Models/User.php[m
[1mindex 3928ce8..a4e16ec 100644[m
[1m--- a/03-execute/23-week/plan-mejoramiento/api/app/Models/User.php[m
[1m+++ b/03-execute/23-week/plan-mejoramiento/api/app/Models/User.php[m
[36m@@ -2,26 +2,22 @@[m
 [m
 namespace App\Models;[m
 [m
[31m-// use Illuminate\Contracts\Auth\MustVerifyEmail;[m
 use Database\Factories\UserFactory;[m
 use Illuminate\Database\Eloquent\Attributes\Fillable;[m
 use Illuminate\Database\Eloquent\Attributes\Hidden;[m
 use Illuminate\Database\Eloquent\Factories\HasFactory;[m
[32m+[m[32muse Illuminate\Database\Eloquent\Relations\HasMany;[m
 use Illuminate\Foundation\Auth\User as Authenticatable;[m
 use Illuminate\Notifications\Notifiable;[m
[32m+[m[32muse Laravel\Sanctum\HasApiTokens;[m
 [m
 #[Fillable(['name', 'email', 'username', 'password', 'role'])][m
 #[Hidden(['password', 'remember_token'])][m
 class User extends Authenticatable[m
 {[m
     /** @use HasFactory<UserFactory> */[m
[31m-    use HasFactory, Notifiable;[m
[32m+[m[32m    use HasApiTokens, HasFactory, Notifiable;[m
 [m
[31m-    /**[m
[31m-     * Get the attributes that should be cast.[m
[31m-     *[m
[31m-     * @return array<string, string>[m
[31m-     */[m
     protected function casts(): array[m
     {[m
         return [[m
[36m@@ -29,4 +25,9 @@[m [mprotected function casts(): array[m
             'password' => 'hashed',[m
         ];[m
     }[m
[31m-}[m
[32m+[m
[32m+[m[32m    public function managedLoans(): HasMany[m
[32m+[m[32m    {[m
[32m+[m[32m        return $this->hasMany(Loan::class, 'manager_id');[m
[32m+[m[32m    }[m
[32m+[m[32m}[m
\ No newline at end of file[m
[1mdiff --git a/03-execute/23-week/plan-mejoramiento/api/bootstrap/app.php b/03-execute/23-week/plan-mejoramiento/api/bootstrap/app.php[m
[1mindex 1085719..5304386 100644[m
[1m--- a/03-execute/23-week/plan-mejoramiento/api/bootstrap/app.php[m
[1m+++ b/03-execute/23-week/plan-mejoramiento/api/bootstrap/app.php[m
[36m@@ -8,6 +8,7 @@[m
 return Application::configure(basePath: dirname(__DIR__))[m
     ->withRouting([m
         web: __DIR__.'/../routes/web.php',[m
[32m+[m[32m        api: __DIR__.'/../routes/api.php',[m
         commands: __DIR__.'/../routes/console.php',[m
         health: '/up',[m
     )[m
[1mdiff --git a/03-execute/23-week/plan-mejoramiento/api/composer.json b/03-execute/23-week/plan-mejoramiento/api/composer.json[m
[1mindex 9b8fbd5..9a90dec 100644[m
[1m--- a/03-execute/23-week/plan-mejoramiento/api/composer.json[m
[1m+++ b/03-execute/23-week/plan-mejoramiento/api/composer.json[m
[36m@@ -8,6 +8,7 @@[m
     "require": {[m
         "php": "^8.3",[m
         "laravel/framework": "^13.17",[m
[32m+[m[32m        "laravel/sanctum": "^4.0",[m
         "laravel/tinker": "^3.0"[m
     },[m
     "require-dev": {[m
