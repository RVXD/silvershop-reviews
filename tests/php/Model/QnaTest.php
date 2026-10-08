<?php

namespace SilverShop\Reviews\Tests\Model;

use SilverShop\Reviews\Model\ProductAnswer;
use SilverShop\Reviews\Model\ProductQuestion;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\SiteConfig\SiteConfig;

class QnaTest extends SapphireTest
{
    protected $usesDatabase = true;

    private function makeAnswer(ProductQuestion $question, string $text, bool $staff, bool $approved): void
    {
        $answer = ProductAnswer::create();
        $answer->Answer = $text;
        $answer->IsStaff = $staff;
        $answer->Approved = $approved;
        $answer->QuestionID = $question->ID;
        $answer->write();
    }

    public function testQuestionAnswersAndApprovedFilter(): void
    {
        $config = SiteConfig::current_site_config();
        $question = ProductQuestion::create();
        $question->Question = 'Does it ship today?';
        $question->Approved = true;
        $question->SubjectID = $config->ID;
        $question->SubjectClass = $config->ClassName;
        $question->write();

        $this->makeAnswer($question, 'Yes, before 5pm.', true, true);
        $this->makeAnswer($question, 'Pending answer', false, false); // unapproved → not shown

        $question = ProductQuestion::get()->byID($question->ID);

        $this->assertSame(2, $question->getAnswerCount(), 'all answers counted in admin');
        $this->assertSame(1, $question->ApprovedAnswers()->count(), 'only approved answers are public');
        $this->assertTrue($question->HasApprovedAnswers());
        $this->assertTrue((bool) $question->ApprovedAnswers()->first()->IsStaff, 'the approved answer is from staff');
    }
}
