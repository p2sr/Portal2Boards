create table evidence_requirements
(
    id int auto_increment,
    `rank` int not null,
    demo bool not null,
    video bool not null,
    active bool not null,
    timestamp datetime not null,
    closed_timestamp datetime default null null,
    constraint evidence_requirements_pk
        primary key (id)
);