'use strict';

const fs = require('node:fs');
const database = require('./database1');

const rosterPath = process.argv[2];

if (!rosterPath) {
    console.error('Usage: npm run import-roster -- <path-to-student-roster.csv>');
    process.exitCode = 1;
} else {
    try {
        const content = fs.readFileSync(rosterPath, 'utf8').replace(/^\uFEFF/, '');
        const rows = content.split(/\r?\n/).map((row) => row.trim()).filter(Boolean);

        if (rows.shift() !== 'studentId') {
            throw new Error('The CSV must start with the header: studentId');
        }
        if (rows.length === 0) {
            throw new Error('The CSV contains no student IDs.');
        }

        const studentIds = rows.map((row) => {
            const value = row.startsWith('"') && row.endsWith('"')
                ? row.slice(1, -1).replace(/""/g, '"')
                : row;
            const studentId = value.trim().toUpperCase();

            if (studentId.includes(',') || !/^[A-Z0-9][A-Z0-9._/-]{0,63}$/.test(studentId)) {
                throw new Error('The CSV contains an invalid student ID. Use one ID per row.');
            }
            return studentId;
        });

        if (new Set(studentIds).size !== studentIds.length) {
            throw new Error('The CSV contains duplicate student IDs.');
        }

        const insertStudent = database.prepare(
            'INSERT INTO approved_students (student_id) VALUES (?) ON CONFLICT(student_id) DO NOTHING'
        );
        database.exec('BEGIN IMMEDIATE');
        try {
            let inserted = 0;
            studentIds.forEach((studentId) => {
                inserted += Number(insertStudent.run(studentId).changes);
            });
            database.exec('COMMIT');
            console.log(`Roster import complete. Added ${inserted} approved student ID(s).`);
        } catch (error) {
            database.exec('ROLLBACK');
            throw error;
        }
    } catch (error) {
        console.error(`Roster import failed: ${error.message}`);
        process.exitCode = 1;
    } finally {
        database.close();
    }
}
