<?php

namespace Database\Seeders;

use App\Models\Course;
use Illuminate\Database\Seeder;

class CourseSeeder extends Seeder
{
    /**
     * Seed a starter catalogue so study groups have courses to attach to.
     *
     * Codes are placeholders; replace them with the real catalogue later.
     */
    public function run(): void
    {
        $courses = [
            ['code' => 'CSC-101', 'name' => 'Introduction to Programming'],
            ['code' => 'CSC-201', 'name' => 'Data Structures'],
            ['code' => 'CSC-202', 'name' => 'Object-Oriented Programming'],
            ['code' => 'CSC-301', 'name' => 'Database Systems'],
            ['code' => 'CSC-302', 'name' => 'Operating Systems'],
            ['code' => 'CSC-303', 'name' => 'Computer Networks'],
            ['code' => 'CSC-401', 'name' => 'Software Engineering'],
            ['code' => 'CSC-402', 'name' => 'Artificial Intelligence'],
            ['code' => 'MTH-101', 'name' => 'Calculus I'],
            ['code' => 'MTH-102', 'name' => 'Calculus II'],
            ['code' => 'MTH-201', 'name' => 'Discrete Mathematics'],
            ['code' => 'MTH-301', 'name' => 'Linear Algebra'],
            ['code' => 'STA-201', 'name' => 'Probability and Statistics'],
            ['code' => 'ENG-101', 'name' => 'Academic Writing'],
            ['code' => 'BUS-101', 'name' => 'Principles of Management'],
        ];

        foreach ($courses as $course) {
            Course::updateOrCreate(['code' => $course['code']], $course);
        }
    }
}
