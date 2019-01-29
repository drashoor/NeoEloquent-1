<?php namespace Vinelab\NeoEloquent\Tests\Functional\Relations\Hybrid;

use Illuminate\Database\Schema\Blueprint;
use Mockery as M;
use Vinelab\NeoEloquent\Eloquent\Relations\Hybrid\HybridRelations;
use Vinelab\NeoEloquent\Tests\TestCase;
use Vinelab\NeoEloquent\Eloquent\Model as NeoEloquent;
use Illuminate\Database\Eloquent\Model as Eloquent;

class Invitation extends Eloquent
{
    use HybridRelations;

    protected $connection = "sqlite";
    protected $fillable = ['name', 'mobile', 'member_id', 'sender_id'];

    public function member()
    {
        return $this->belongsToHybrid(Member::class, 'member_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class);
    }
}

class User extends Eloquent
{
    use HybridRelations;

    protected $connection = "sqlite";
    protected $fillable = ['name'];

    public function member()
    {
        return $this->hasOneHybrid(Member::class);
    }
}

class Member extends NeoEloquent
{
    use HybridRelations;

    protected $label = 'member';
    protected $table = 'member';
    protected $connection = "neo4j";
    protected $fillable = ['name', 'user_id'];

    public function invitation()
    {
        return $this->hasOneHybrid(Invitation::class, 'member_id');
    }

    public function user()
    {
        return $this->belongsToHybrid(User::class);
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class, 'member');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'father');
    }

    public function father()
    {
        return $this->belongsTo(self::class, 'father');
    }
}

class Contact extends NeoEloquent
{
    use HybridRelations;

    protected $label = 'contact';
    protected $table = 'contact';
    protected $connection = "neo4j";
    protected $fillable = ['email'];

    public function member()
    {
        return $this->belongsTo(Member::class, 'member');
    }
}


class BelongsToRelationTest extends TestCase
{
    protected $db;

    protected $schema;


    public function tearDown()
    {
        M::close();
        $this->schema->dropIfExists('invitations');
        $this->schema->dropIfExists('users');
        Member::where("id", ">", -1)->delete();

        parent::tearDown();
    }

    public function setUp()
    {
        parent::setUp();
        $this->prepareDatabase();
        Invitation::setConnectionResolver($this->resolver);
        Member::setConnectionResolver($this->resolver);

        $this->schema->create('users', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name');
            $t->timestamps();
        });
        $this->schema->create('invitations', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name');
            $t->string('mobile');
            $t->integer('member_id');
            $t->integer('sender_id')->nullable();
            $t->timestamps();
        });
    }

    public function testLazyLoadingBelongsToDepth1()
    {
        $user = User::create(["name" => "yassir"]);
        $member = Member::create(["name" => "yassir", "user_id" => $user->id]);

        $this->assertEquals($member->user->id, $user->id);
    }

    public function testLazyLoadingBelongsToDepth2()
    {
        $this->markTestSkipped();
        $user = User::create(["name" => "yassir"]);
        $member = Member::create(["name" => "yassir", "user_id" => $user->id]);
        $contact = $member->contacts()->save(Contact::create(["email" => "yassir.awad@dce.sa"]))->related();
    }

    public function testEgarLoadingBelongsToUsginLoadDepth2()
    {
        $user = User::create(["name" => "yassir"]);
        $member = Member::create(["name" => "yassir", "user_id" => $user->id]);
        $invitation = Invitation::create(['name' => 'Daughter', 'sender_id' => $user->id, 'mobile' => '0565656', "member_id" => $member->id]);

        $this->assertEquals($member->id, $invitation->load("sender.member")->sender->member->id);
        $this->assertEquals($user->id, $invitation->load("member.user")->member->user->id);
    }

    public function testEgarLoadingUsingWithDepth1()
    {
        $user = User::create(["name" => "yassir"]);
        Member::create(["name" => "yassir", "user_id" => $user->id]);
        $member = Member::with(["user"])->first();

        $this->assertTrue($member->relationLoaded("user"));
        $this->assertEquals($member->user->id, $user->id);
    }

    public function testEgarLoadingUsingWithDepth2()
    {
        $user = User::create(["name" => "yassir"]);
        $member = Member::create(["name" => "yassir", "user_id" => $user->id]);
        $member->contacts()->save(Contact::create(["email" => "yassir.awad@dce.sa"]));
        $contact = Contact::with(["member.user"])->first();

        $this->assertTrue($contact->relationLoaded("member"));
        $this->assertEquals($contact->member->id, $member->id);
        $this->assertTrue($contact->member->relationLoaded("user"));
        $this->assertEquals($contact->member->user->id, $user->id);
    }
}
