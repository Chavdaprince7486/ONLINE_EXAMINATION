USE online_examination;

INSERT INTO categories
(
    category_name,
    description,
    icon,
    status
)
VALUES
(
    'UPSC',
    'Civil Services and UPSC preparation with focused practice exams.',
    'fa-solid fa-building-columns',
    'Active'
),
(
    'GPSC',
    'Gujarat Public Service Commission preparation and practice.',
    'fa-solid fa-landmark',
    'Active'
),
(
    'NEET',
    'Medical entrance preparation with structured practice questions.',
    'fa-solid fa-user-doctor',
    'Active'
),
(
    'JEE',
    'Engineering entrance preparation with focused practice exams.',
    'fa-solid fa-atom',
    'Active'
),
(
    'Forest',
    'Forest and environment related competitive examination preparation.',
    'fa-solid fa-tree',
    'Active'
),
(
    'Railway',
    'Railway recruitment examination practice and preparation.',
    'fa-solid fa-train',
    'Active'
),
(
    'SSC',
    'Staff Selection Commission examination preparation.',
    'fa-solid fa-file-lines',
    'Active'
),
(
    'Banking',
    'Banking and financial sector competitive exam preparation.',
    'fa-solid fa-building-columns',
    'Active'
),
(
    'Police',
    'Police recruitment and competitive examination preparation.',
    'fa-solid fa-shield-halved',
    'Active'
),
(
    'Talati',
    'Talati and Gujarat government recruitment exam preparation.',
    'fa-solid fa-file-signature',
    'Active'
),
(
    'University',
    'University and college examination preparation.',
    'fa-solid fa-graduation-cap',
    'Active'
),
(
    'School',
    'School-level examination and academic preparation.',
    'fa-solid fa-school',
    'Active'
)
ON DUPLICATE KEY UPDATE
    description = VALUES(description),
    icon = VALUES(icon),
    status = VALUES(status);