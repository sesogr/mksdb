delimiter @
drop function if exists strip_punctuation @
create function strip_punctuation(text varchar) returns text deterministic
begin
    declare text1, text2, text3, text4, text5, text6, text7 varchar;
    set text1 = replace(replace(replace(replace(replace(text, '`', ' '), '=', ' '), '[', ' '), ']', ' '), ';', ' ');
    set text2 = replace(replace(replace(replace(replace(text1, '\'', ' '), '\\', ' '), ',', ' '), '.', ' '), '/', ' ');
    set text3 = replace(replace(replace(replace(replace(text2, '~', ' '), '!', ' '), '@', ' '), '#', ' '), '$', ' ');
    set text4 = replace(replace(replace(replace(replace(text3, '%', ' '), '^', ' '), '&', ' '), '*', ' '), '(', ' ');
    set text5 = replace(replace(replace(replace(replace(text4, ')', ' '), '_', ' '), '+', ' '), '\{', ' '), '}', ' ');
    set text6 = replace(replace(replace(replace(replace(text5, ':', ' '), '"', ' '), '|', ' '), '<', ' '), '>', ' ');
    set text7 = replace(replace(replace(replace(text6, '?', ' '), '         ', ' '), '     ', ' '), '   ', ' ');
    return replace(replace(text7, '  ', ' '), ' ', ' ');
end @
delimiter ;
