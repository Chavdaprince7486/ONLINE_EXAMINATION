<?php
declare(strict_types=1);
require_once __DIR__ . '/notifications.php';

function examsphere_event_new_exam(PDO $conn,int $examId,string $title,string $examType='Exam',string $status='Draft'):void{
    if(strtolower(trim($status))==='draft')return;

    try{
        examsphere_notify_students(
            $conn,
            'New '.$examType.' Exam Available',
            'A new exam "'.$title.'" is now available on ExamSphere. Please check the exam section for details.',
            'exam',
            'exam',
            $examId
        );
    }catch(Throwable $e){
        error_log('New exam notification failed: '.$e->getMessage());
    }
}

function examsphere_event_exam_updated(PDO $conn,int $examId,string $title):void{
    try{
        examsphere_notify_students(
            $conn,
            'Exam Updated',
            'The exam "'.$title.'" has been updated. Please check the latest exam details.',
            'exam',
            'exam',
            $examId
        );
    }catch(Throwable $e){
        error_log('Exam update notification failed: '.$e->getMessage());
    }
}

function examsphere_event_material(PDO $conn,int $materialId,string $title,string $action='added'):void{
    try{
        examsphere_notify_students(
            $conn,
            'Study Material '.ucfirst($action),
            'Study material "'.$title.'" has been '.$action.' on ExamSphere.',
            'material',
            'material',
            $materialId
        );
    }catch(Throwable $e){
        error_log('Material notification failed: '.$e->getMessage());
    }
}

function examsphere_event_teacher_content(
    PDO $conn,
    string $title,
    string $message,
    string $type='question',
    ?int $id=null
):void{
    try{
        examsphere_notify_students(
            $conn,
            $title,
            $message,
            $type,
            $type,
            $id
        );
    }catch(Throwable $e){
        error_log('Teacher content notification failed: '.$e->getMessage());
    }
}

function examsphere_event_admin_academic(
    PDO $conn,
    string $type,
    int $id,
    string $name,
    string $action='added'
):void{
    try{
        examsphere_notify_teachers(
            $conn,
            'Academic '.ucfirst($action).' - '.ucfirst($type),
            'The '.$type.' "'.$name.'" has been '.$action.' by the administrator. Please check the latest academic structure.',
            $type,
            $type,
            $id
        );
    }catch(Throwable $e){
        error_log('Admin academic notification failed: '.$e->getMessage());
    }
}