drop table if exists mks_x_collection_song;
drop table if exists mks_x_composer_song;
drop table if exists mks_x_cover_artist_song;
drop table if exists mks_x_genre_song;
drop table if exists mks_x_performer_song;
drop table if exists mks_x_publication_place_song;
drop table if exists mks_x_publisher_song;
drop table if exists mks_x_source_song;
drop table if exists mks_x_writer_song;

drop table if exists mks_city;
drop table if exists mks_collection;
drop table if exists mks_genre;
drop table if exists mks_person;
drop table if exists mks_publisher;
drop table if exists mks_song;
drop table if exists mks_source;

create table if not exists mks_city (
    id int unsigned not null primary key auto_increment,
    name text
);
create table if not exists mks_collection (
    id int unsigned not null primary key auto_increment,
    name text
);
create table if not exists mks_genre (
    id int unsigned not null primary key auto_increment,
    name text
);
create table if not exists mks_person (
    id int unsigned not null primary key auto_increment,
    name text
);
create table if not exists mks_publisher (
    id int unsigned not null primary key auto_increment,
    name text
);
create table if not exists mks_song (
    id int unsigned not null primary key auto_increment,
    name text,
    copyright_year text,
    copyright_remark text,
    created_on text,
    label text,
    publisher_series text,
    publisher_number text,
    record_number text,
    origin text,
    dedication text,
    review text,
    addition text,
    index_no text
);
create table if not exists mks_source (
    id int unsigned not null primary key auto_increment,
    name text
);

create table if not exists mks_x_collection_song (
    song_id int unsigned not null,
    collection_id int unsigned not null,
    position tinyint unsigned default null,
    annotation text default null,
    index(song_id),
    index(collection_id),
    foreign key (song_id) references mks_song (id),
    foreign key (collection_id) references mks_collection (id)
);
create table if not exists mks_x_composer_song (
    song_id int unsigned not null,
    composer_id int unsigned not null,
    position tinyint unsigned default null,
    annotation text default null,
    index(song_id),
    index(composer_id),
    foreign key (song_id) references mks_song (id),
    foreign key (composer_id) references mks_person (id)
);
create table if not exists mks_x_cover_artist_song (
    song_id int unsigned not null,
    cover_artist_id int unsigned not null,
    position tinyint unsigned default null,
    annotation text default null,
    index(song_id),
    index(cover_artist_id),
    foreign key (song_id) references mks_song (id),
    foreign key (cover_artist_id) references mks_person (id)
);
create table if not exists mks_x_genre_song (
    song_id int unsigned not null,
    genre_id int unsigned not null,
    position tinyint unsigned default null,
    annotation text default null,
    index(song_id),
    index(genre_id),
    foreign key (song_id) references mks_song (id),
    foreign key (genre_id) references mks_genre (id)
);
create table if not exists mks_x_performer_song (
    song_id int unsigned not null,
    performer_id int unsigned not null,
    position tinyint unsigned default null,
    annotation text default null,
    index(song_id),
    index(performer_id),
    foreign key (song_id) references mks_song (id),
    foreign key (performer_id) references mks_person (id)
);
create table if not exists mks_x_publication_place_song (
    song_id int unsigned not null,
    publication_place_id int unsigned not null,
    position tinyint unsigned default null,
    annotation text default null,
    index(song_id),
    index(publication_place_id),
    foreign key (song_id) references mks_song (id),
    foreign key (publication_place_id) references mks_city (id)
);
create table if not exists mks_x_publisher_song (
    song_id int unsigned not null,
    publisher_id int unsigned not null,
    position tinyint unsigned default null,
    annotation text default null,
    index(song_id),
    index(publisher_id),
    foreign key (song_id) references mks_song (id),
    foreign key (publisher_id) references mks_publisher (id)
);
create table if not exists mks_x_source_song (
    song_id int unsigned not null,
    source_id int unsigned not null,
    position tinyint unsigned default null,
    annotation text default null,
    index(song_id),
    index(source_id),
    foreign key (song_id) references mks_song (id),
    foreign key (source_id) references mks_source (id)
);
create table if not exists mks_x_writer_song (
    song_id int unsigned not null,
    writer_id int unsigned not null,
    position tinyint unsigned default null,
    annotation text default null,
    index(song_id),
    index(writer_id),
    foreign key (song_id) references mks_song (id),
    foreign key (writer_id) references mks_person (id)
);

insert into mks_city (name)
    select distinct
        nullif(trim(concat(substr(Verlagsort, 1, char_length(Verlagsort) - 3), trim(trailing '?' from replace(replace(substr(Verlagsort, -3), '(?)', ''), '[?]', '')))), '') name
    from `20201217-oeaw-schlager-db`
    having name is not null;

insert into mks_collection (name)
    select distinct
        nullif(trim(concat(substr(Sammlungen, 1, char_length(Sammlungen) - 3), trim(trailing '?' from replace(replace(substr(Sammlungen, -3), '(?)', ''), '[?]', '')))), '') name
    from `20201217-oeaw-schlager-db`
    having name is not null;

insert into mks_genre (name)
    select distinct
        nullif(trim(concat(substr(Gattung, 1, char_length(Gattung) - 3), trim(trailing '?' from replace(replace(substr(Gattung, -3), '(?)', ''), '[?]', '')))), '') name
    from `20201217-oeaw-schlager-db`
    having name is not null;

insert into mks_person (name)
    select distinct
        nullif(trim(concat(substr(name, 1, char_length(name) - 3), trim(trailing '?' from replace(replace(substr(name, -3), '(?)', ''), '[?]', '')))), '') name
    from (
             select `Komponist 1` name from `20201217-oeaw-schlager-db`
             union select `Komponist 2` name from `20201217-oeaw-schlager-db`
             union select `Komponist 3` name from `20201217-oeaw-schlager-db`
             union select `Komponist 4` name from `20201217-oeaw-schlager-db`
             union select `Texter 1` name from `20201217-oeaw-schlager-db`
             union select `Texter 2` name from `20201217-oeaw-schlager-db`
             union select `Texter 3` name from `20201217-oeaw-schlager-db`
             union select `Texter 4` name from `20201217-oeaw-schlager-db`
             union select `Graphiker` name from `20201217-oeaw-schlager-db`
             union select `Interpreten` name from `20201217-oeaw-schlager-db`
             union select `Interpret 2` name from `20201217-oeaw-schlager-db`
             union select `Interpret 3` name from `20201217-oeaw-schlager-db`
             union select `Interpret 4` name from `20201217-oeaw-schlager-db`
             union select `Interpret 5` name from `20201217-oeaw-schlager-db`
             union select `Interpret 6` name from `20201217-oeaw-schlager-db`
         ) c
    having name is not null
    order by name;

insert into mks_publisher (name)
    select distinct
        nullif(trim(concat(substr(Verlag, 1, char_length(Verlag) - 3), trim(trailing '?' from replace(replace(substr(Verlag, -3), '(?)', ''), '[?]', '')))), '') name
    from `20201217-oeaw-schlager-db`
    having name is not null;

insert into mks_song
    select
        id,
        Titel name,
        Copyright copyright_year,
        Copyrightvermerk copyright_remark,
        Entstehung created_on,
        Label label,
        Verlagsreihe publisher_series,
        Verlagsnummer publisher_number,
        `Plattennr.` record_number,
        Herkunft origin,
        Widmung dedication,
        Kritik review,
        Ergänzung addition,
        `Index` index_no
    from `20201217-oeaw-schlager-db`;

insert into mks_source (name)
    select distinct
        nullif(trim(concat(substr(Quelle, 1, char_length(Quelle) - 3), trim(trailing '?' from replace(replace(substr(Quelle, -3), '(?)', ''), '[?]', '')))), '') name
    from `20201217-oeaw-schlager-db`
    having name is not null;

insert into mks_x_collection_song (song_id, collection_id, annotation)
    select distinct song_id, collection_id, if(trim(name_orig) = trim(name), null, trim(substr(trim(name_orig), char_length(name) - char_length(trim(name_orig))))) annotation
    from
        (
            select distinct
                a.id song_id,
                b.id collection_id,
                a.Sammlungen name_orig,
                b.name
            from `20201217-oeaw-schlager-db` a
                join mks_collection b on b.name = trim(concat(substr(a.Sammlungen, 1, char_length(a.Sammlungen) - 3), trim(trailing '?' from replace(replace(substr(a.Sammlungen, -3), '(?)', ''), '[?]', ''))))
            where a.Sammlungen is not null
        ) c
    order by song_id;

insert into mks_x_composer_song (song_id, composer_id, position, annotation)
    select distinct song_id, composer_id, position, if(trim(name_orig) = trim(name), null, trim(substr(trim(name_orig), char_length(name) - char_length(trim(name_orig))))) annotation
    from
        (
            select distinct a.id song_id, b.id composer_id, 1 position, a.`Komponist 1` name_orig, b.name
            from `20201217-oeaw-schlager-db` a
                join mks_person b on b.name = trim(substr(a.`Komponist 1`, 1, char_length(a.`Komponist 1`) - 3 + char_length(trim(trailing '?' from replace(replace(substr(a.`Komponist 1`, -3), '(?)', ''), '[?]', '')))))
            where a.`Komponist 1` is not null
            union
            select distinct a.id song_id, b.id composer_id, 2 position, a.`Komponist 2` name_orig, b.name
            from `20201217-oeaw-schlager-db` a
                join mks_person b on b.name = trim(substr(a.`Komponist 2`, 1, char_length(a.`Komponist 2`) - 3 + char_length(trim(trailing '?' from replace(replace(substr(a.`Komponist 2`, -3), '(?)', ''), '[?]', '')))))
            where a.`Komponist 2` is not null
            union
            select distinct a.id song_id, b.id composer_id, 3 position, a.`Komponist 3` name_orig, b.name
            from `20201217-oeaw-schlager-db` a
                join mks_person b on b.name = trim(substr(a.`Komponist 3`, 1, char_length(a.`Komponist 3`) - 3 + char_length(trim(trailing '?' from replace(replace(substr(a.`Komponist 3`, -3), '(?)', ''), '[?]', '')))))
            where a.`Komponist 3` is not null
            union
            select distinct a.id song_id, b.id composer_id, 4 position, a.`Komponist 4` name_orig, b.name
            from `20201217-oeaw-schlager-db` a
                join mks_person b on b.name = trim(substr(a.`Komponist 4`, 1, char_length(a.`Komponist 4`) - 3 + char_length(trim(trailing '?' from replace(replace(substr(a.`Komponist 4`, -3), '(?)', ''), '[?]', '')))))
            where a.`Komponist 4` is not null
        ) c
    order by song_id, position;

insert into mks_x_cover_artist_song (song_id, cover_artist_id, annotation)
    select distinct song_id, cover_artist_id, if(trim(name_orig) = trim(name), null, trim(substr(trim(name_orig), char_length(name) - char_length(trim(name_orig))))) annotation
    from
        (
            select distinct
                a.id song_id,
                b.id cover_artist_id,
                a.`Graphiker` name_orig,
                b.name
            from `20201217-oeaw-schlager-db` a
                join mks_person b on b.name = trim(concat(substr(a.`Graphiker`, 1, char_length(a.`Graphiker`) - 3), trim(trailing '?' from replace(replace(substr(a.`Graphiker`, -3), '(?)', ''), '[?]', ''))))
            where a.`Graphiker` is not null
        ) c
    order by song_id;

insert into mks_x_genre_song (song_id, genre_id, annotation)
    select distinct song_id, genre_id, if(trim(name_orig) = trim(name), null, trim(substr(trim(name_orig), char_length(name) - char_length(trim(name_orig))))) annotation
    from
        (
            select distinct
                a.id song_id,
                b.id genre_id,
                a.Gattung name_orig,
                b.name
            from `20201217-oeaw-schlager-db` a
                join mks_genre b on b.name = trim(concat(substr(a.Gattung, 1, char_length(a.Gattung) - 3), trim(trailing '?' from replace(replace(substr(a.Gattung, -3), '(?)', ''), '[?]', ''))))
            where a.Gattung is not null
        ) c
    order by song_id;

insert into mks_x_performer_song (song_id, performer_id, position, annotation)
    select distinct song_id, performer_id, position, if(trim(name_orig) = trim(name), null, trim(substr(trim(name_orig), char_length(name) - char_length(trim(name_orig))))) annotation
    from
        (
            select distinct
                a.id song_id,
                b.id performer_id,
                1 position,
                a.`Interpreten` name_orig,
                b.name
            from `20201217-oeaw-schlager-db` a
                join mks_person b on b.name = trim(concat(substr(a.`Interpreten`, 1, char_length(a.`Interpreten`) - 3), trim(trailing '?' from replace(replace(substr(a.`Interpreten`, -3), '(?)', ''), '[?]', ''))))
            where a.`Interpreten` is not null
            union
            select distinct
                a.id song_id,
                b.id performer_id,
                2 position,
                a.`Interpret 2` name_orig,
                b.name
            from `20201217-oeaw-schlager-db` a
                join mks_person b on b.name = trim(substr(a.`Interpret 2`, 1, char_length(a.`Interpret 2`) - 3 + char_length(trim(trailing '?' from replace(replace(substr(a.`Interpret 2`, -3), '(?)', ''), '[?]', '')))))
            where a.`Interpret 2` is not null
            union
            select distinct
                a.id song_id,
                b.id performer_id,
                3 position,
                a.`Interpret 3` name_orig,
                b.name
            from `20201217-oeaw-schlager-db` a
                join mks_person b on b.name = trim(substr(a.`Interpret 3`, 1, char_length(a.`Interpret 3`) - 3 + char_length(trim(trailing '?' from replace(replace(substr(a.`Interpret 3`, -3), '(?)', ''), '[?]', '')))))
            where a.`Interpret 3` is not null
            union
            select distinct
                a.id song_id,
                b.id performer_id,
                4 position,
                a.`Interpret 4` name_orig,
                b.name
            from `20201217-oeaw-schlager-db` a
                join mks_person b on b.name = trim(substr(a.`Interpret 4`, 1, char_length(a.`Interpret 4`) - 3 + char_length(trim(trailing '?' from replace(replace(substr(a.`Interpret 4`, -3), '(?)', ''), '[?]', '')))))
            where a.`Interpret 4` is not null
            union
            select distinct
                a.id song_id,
                b.id performer_id,
                5 position,
                a.`Interpret 5` name_orig,
                b.name
            from `20201217-oeaw-schlager-db` a
                join mks_person b on b.name = trim(substr(a.`Interpret 5`, 1, char_length(a.`Interpret 5`) - 3 + char_length(trim(trailing '?' from replace(replace(substr(a.`Interpret 5`, -3), '(?)', ''), '[?]', '')))))
            where a.`Interpret 5` is not null
            union
            select distinct
                a.id song_id,
                b.id performer_id,
                6 position,
                a.`Interpret 6` name_orig,
                b.name
            from `20201217-oeaw-schlager-db` a
                join mks_person b on b.name = trim(substr(a.`Interpret 6`, 1, char_length(a.`Interpret 6`) - 3 + char_length(trim(trailing '?' from replace(replace(substr(a.`Interpret 6`, -3), '(?)', ''), '[?]', '')))))
            where a.`Interpret 6` is not null
        ) c
    order by song_id, position;

insert into mks_x_publication_place_song (song_id, publication_place_id, annotation)
    select distinct song_id, publication_place_id, if(trim(name_orig) = trim(name), null, trim(substr(trim(name_orig), char_length(name) - char_length(trim(name_orig))))) annotation
    from
        (
            select distinct
                a.id song_id,
                b.id publication_place_id,
                a.Verlagsort name_orig,
                b.name
            from `20201217-oeaw-schlager-db` a
                join mks_city b on b.name = trim(concat(substr(a.Verlagsort, 1, char_length(a.Verlagsort) - 3), trim(trailing '?' from replace(replace(substr(a.Verlagsort, -3), '(?)', ''), '[?]', ''))))
            where a.Verlagsort is not null
        ) c
    order by song_id;

insert into mks_x_publisher_song (song_id, publisher_id, annotation)
    select distinct song_id, publisher_id, if(trim(name_orig) = trim(name), null, trim(substr(trim(name_orig), char_length(name) - char_length(trim(name_orig))))) annotation
    from
        (
            select distinct
                a.id song_id,
                b.id publisher_id,
                a.Verlag name_orig,
                b.name
            from `20201217-oeaw-schlager-db` a
                join mks_publisher b on b.name = trim(concat(substr(a.Verlag, 1, char_length(a.Verlag) - 3), trim(trailing '?' from replace(replace(substr(a.Verlag, -3), '(?)', ''), '[?]', ''))))
            where a.Verlag is not null
        ) c
    order by song_id;

insert into mks_x_source_song (song_id, source_id, annotation)
    select distinct song_id, source_id, if(trim(name_orig) = trim(name), null, trim(substr(trim(name_orig), char_length(name) - char_length(trim(name_orig))))) annotation
    from
        (
            select distinct
                a.id song_id,
                b.id source_id,
                a.Quelle name_orig,
                b.name
            from `20201217-oeaw-schlager-db` a
                join mks_source b on b.name = trim(concat(substr(a.Quelle, 1, char_length(a.Quelle) - 3), trim(trailing '?' from replace(replace(substr(a.Quelle, -3), '(?)', ''), '[?]', ''))))
            where a.Quelle is not null
        ) c
    order by song_id;

insert into mks_x_writer_song (song_id, writer_id, position, annotation)
    select distinct song_id, writer_id, position, if(trim(name_orig) = trim(name), null, trim(substr(trim(name_orig), char_length(name) - char_length(trim(name_orig))))) annotation
    from
        (
            select distinct a.id song_id, b.id writer_id, 1 position, a.`Texter 1` name_orig, b.name
            from `20201217-oeaw-schlager-db` a
                join mks_person b on b.name = trim(substr(a.`Texter 1`, 1, char_length(a.`Texter 1`) - 3 + char_length(trim(trailing '?' from replace(replace(substr(a.`Texter 1`, -3), '(?)', ''), '[?]', '')))))
            where a.`Texter 1` is not null
            union
            select distinct a.id song_id, b.id composer_id, 2 position, a.`Texter 2` name_orig, b.name
            from `20201217-oeaw-schlager-db` a
                join mks_person b on b.name = trim(substr(a.`Texter 2`, 1, char_length(a.`Texter 2`) - 3 + char_length(trim(trailing '?' from replace(replace(substr(a.`Texter 2`, -3), '(?)', ''), '[?]', '')))))
            where a.`Texter 2` is not null
            union
            select distinct a.id song_id, b.id composer_id, 3 position, a.`Texter 3` name_orig, b.name
            from `20201217-oeaw-schlager-db` a
                join mks_person b on b.name = trim(substr(a.`Texter 3`, 1, char_length(a.`Texter 3`) - 3 + char_length(trim(trailing '?' from replace(replace(substr(a.`Texter 3`, -3), '(?)', ''), '[?]', '')))))
            where a.`Texter 3` is not null
            union
            select distinct a.id song_id, b.id composer_id, 4 position, a.`Texter 4` name_orig, b.name
            from `20201217-oeaw-schlager-db` a
                join mks_person b on b.name = trim(substr(a.`Texter 4`, 1, char_length(a.`Texter 4`) - 3 + char_length(trim(trailing '?' from replace(replace(substr(a.`Texter 4`, -3), '(?)', ''), '[?]', '')))))
            where a.`Texter 4` is not null
        ) c
    order by song_id, position;

-- select max(length(name)) from mks_city;             -- 50
-- select max(length(name)) from mks_collection;       -- 350
-- select max(length(name)) from mks_genre;            -- 93
-- select max(length(name)) from mks_person;           -- 326
-- select max(length(name)) from mks_publisher;        -- 184
-- select max(length(name)) from mks_song;             -- 129
-- select max(length(copyright_year)) from mks_song;   -- 6
-- select max(length(copyright_remark)) from mks_song; -- 147
-- select max(length(created_on)) from mks_song;       -- 22
-- select max(length(label)) from mks_song;            -- 537
-- select max(length(publisher_series)) from mks_song; -- 108
-- select max(length(publisher_number)) from mks_song; -- 15
-- select max(length(record_number)) from mks_song;    -- 47
-- select max(length(origin)) from mks_song;           -- 449
-- select max(length(dedication)) from mks_song;       -- 240
-- select max(length(review)) from mks_song;           -- 4581
-- select max(length(addition)) from mks_song;         -- 4501
-- select max(length(index_no)) from mks_song;         -- 5
-- select max(length(name)) from mks_source;           -- 501

alter table mks_city modify column name varchar(50) default null;
alter table mks_collection modify column name varchar(350) default null;
alter table mks_genre modify column name varchar(93) default null;
alter table mks_person modify column name varchar(326) default null;
alter table mks_publisher modify column name varchar(184) default null;
alter table mks_song
    modify column name varchar(129) default null,
    modify column copyright_year varchar(6) default null,
    modify column copyright_remark varchar(147) default null,
    modify column created_on varchar(22) default null,
    modify column label varchar(537) default null,
    modify column publisher_series varchar(108) default null,
    modify column publisher_number varchar(15) default null,
    modify column record_number varchar(47) default null,
    modify column origin varchar(449) default null,
    modify column dedication varchar(240) default null,
    modify column review varchar(4581) default null,
    modify column addition varchar(4501) default null,
    modify column index_no varchar(5) default null;
alter table mks_source modify column name varchar(501) default null;

alter table mks_x_collection_song modify column annotation varchar(3) default null;
alter table mks_x_composer_song modify column annotation varchar(3) default null;
alter table mks_x_cover_artist_song modify column annotation varchar(3) default null;
alter table mks_x_genre_song modify column annotation varchar(3) default null;
alter table mks_x_performer_song modify column annotation varchar(3) default null;
alter table mks_x_publication_place_song modify column annotation varchar(3) default null;
alter table mks_x_publisher_song modify column annotation varchar(3) default null;
alter table mks_x_source_song modify column annotation varchar(3) default null;
alter table mks_x_writer_song modify column annotation varchar(3) default null;
