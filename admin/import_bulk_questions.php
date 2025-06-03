<?php
$page_title = "বাল্ক প্রশ্ন ইম্পোর্ট";
$admin_base_url = ''; // Current directory is admin/
require_once '../includes/db_connect.php';
require_once 'includes/auth_check.php';
require_once '../includes/functions.php'; // functions.php তে যদি escape_html না থাকে, তবে যোগ করতে হবে
require_once 'includes/header.php';
?>

<div class="container-fluid">
    <h1 class="mt-4 mb-3"><?php echo $page_title; ?></h1>

    <div class="card mb-4">
        <div class="card-header">টেক্সট থেকে প্রশ্ন ইম্পোর্ট করুন</div>
        <div class="card-body">
            <p>প্রশ্ন এবং অপশনগুলো নিচের ফর্ম্যাটে লিখুন। প্রতিটি প্রশ্ন এবং অপশন নতুন লাইনে লিখুন। যেমন:</p>
            <pre class="bg-light p-2 rounded small">
1. প্রথম প্রশ্ন কোনটি?
a. অপশন ক
b. অপশন খ
*c. অপশন গ
d. অপশন ঘ
=এখানে ব্যাখ্যা যুক্ত হবে। না হলে খালি রাখবেন।

2. দ্বিতীয় প্রশ্ন কী?
a. অপশন ক
*b. অপশন খ
c. অপশন গ
d. অপশন ঘ
=এখানে ব্যাখ্যা যুক্ত হবে। না হলে খালি রাখবেন।

3. ৩য় প্রশ্ন কী?
a. অপশন ক
b. অপশন খ
c. অপশন গ
*d. অপশন ঘ
=এখানে ব্যাখ্যা যুক্ত হবে। না হলে খালি রাখবেন।
            </pre>
            <form action="add_quiz.php" method="post">
                <div class="mb-3">
                    <label for="bulk_questions_text_import" class="form-label">প্রশ্নগুলো এখানে পেস্ট করুন:</label>
                    <textarea class="form-control" id="bulk_questions_text_import" name="bulk_questions_text_import" rows="15" placeholder="উপরের ফরম্যাট অনুযায়ী প্রশ্ন ও অপশন লিখুন..." required></textarea>
                </div>
                <button type="submit" name="prepare_questions_from_bulk" class="btn btn-primary">প্রশ্নগুলো প্রস্তুত করুন ও কুইজ তৈরির পেইজে যান</button>
            </form>
            <small class="form-text text-muted d-block mt-2">
                এই পেইজ থেকে প্রশ্নগুলো `নতুন কুইজ যোগ করুন` পেইজে পাঠানো হবে। সেখানে আপনি কুইজের অন্যান্য তথ্য (শিরোনাম, সময়, স্ট্যাটাস ইত্যাদি) এবং প্রতিটি প্রশ্নের সঠিক উত্তর, ছবি (যদি থাকে) ও ব্যাখ্যা যোগ করতে পারবেন।
            </small>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>