<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Immutable, official-language reference adapted from XCore native data. */
final class Version20261116192100 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO symsvel_languages (iso_code, name, active, pre_open) VALUES ('am','አማርኛ',FALSE,FALSE), ('ar','العربية',FALSE,FALSE), ('ay','ay',FALSE,FALSE), ('az','azərbaycan',FALSE,FALSE), ('be','беларуская',FALSE,FALSE), ('bg','български',FALSE,FALSE), ('bi','bi',FALSE,FALSE), ('bn','বাংলা',FALSE,FALSE), ('bs','bosanski',FALSE,FALSE), ('ca','català',FALSE,FALSE), ('ch','ch',FALSE,FALSE), ('cs','čeština',FALSE,FALSE), ('da','dansk',FALSE,FALSE), ('de','Deutsch',FALSE,FALSE), ('dv','ދިވެހިބަސް',FALSE,FALSE), ('dz','རྫོང་ཁ',FALSE,FALSE), ('el','Ελληνικά',FALSE,FALSE), ('en','English',FALSE,FALSE), ('es','español',FALSE,FALSE), ('et','eesti',FALSE,FALSE), ('fa','فارسی',FALSE,FALSE), ('fi','suomi',FALSE,FALSE), ('fil','Filipino',FALSE,FALSE), ('fj','fj',FALSE,FALSE), ('fo','føroyskt',FALSE,FALSE)");
        $this->addSql("INSERT INTO symsvel_languages (iso_code, name, active, pre_open) VALUES ('fr','français',FALSE,FALSE), ('ga','Gaeilge',FALSE,FALSE), ('gil','gil',FALSE,FALSE), ('gn','avañe’ẽ',FALSE,FALSE), ('gsw','Schwiizertüütsch',FALSE,FALSE), ('gv','Gaelg',FALSE,FALSE), ('he','עברית',FALSE,FALSE), ('hi','हिन्दी',FALSE,FALSE), ('hif','hif',FALSE,FALSE), ('ho','ho',FALSE,FALSE), ('hr','hrvatski',FALSE,FALSE), ('ht','Kreyòl Ayisyen',FALSE,FALSE), ('hu','magyar',FALSE,FALSE), ('hy','հայերեն',FALSE,FALSE), ('id','Indonesia',FALSE,FALSE), ('is','íslenska',FALSE,FALSE), ('it','italiano',FALSE,FALSE), ('ja','日本語',FALSE,FALSE), ('ka','ქართული',FALSE,FALSE), ('kk','қазақ тілі',FALSE,FALSE), ('kl','kalaallisut',FALSE,FALSE), ('km','ខ្មែរ',FALSE,FALSE), ('ko','한국어',FALSE,FALSE), ('ky','кыргызча',FALSE,FALSE), ('lb','Lëtzebuergesch',FALSE,FALSE)");
        $this->addSql("INSERT INTO symsvel_languages (iso_code, name, active, pre_open) VALUES ('lo','ລາວ',FALSE,FALSE), ('lt','lietuvių',FALSE,FALSE), ('lv','latviešu',FALSE,FALSE), ('mg','Malagasy',FALSE,FALSE), ('mh','mh',FALSE,FALSE), ('mi','Māori',FALSE,FALSE), ('mk','македонски',FALSE,FALSE), ('mn','монгол',FALSE,FALSE), ('ms','Melayu',FALSE,FALSE), ('mt','Malti',FALSE,FALSE), ('my','မြန်မာ',FALSE,FALSE), ('na','na',FALSE,FALSE), ('nb','norsk bokmål',FALSE,FALSE), ('nd','isiNdebele',FALSE,FALSE), ('ne','नेपाली',FALSE,FALSE), ('niu','niu',FALSE,FALSE), ('nl','Nederlands',FALSE,FALSE), ('nn','norsk nynorsk',FALSE,FALSE), ('no','norsk',FALSE,FALSE), ('ny','Nyanja',FALSE,FALSE), ('pap','Papiamentu',FALSE,FALSE), ('pau','pau',FALSE,FALSE), ('pl','polski',FALSE,FALSE), ('ps','پښتو',FALSE,FALSE), ('pt','português',FALSE,FALSE)");
        $this->addSql("INSERT INTO symsvel_languages (iso_code, name, active, pre_open) VALUES ('qu','Runasimi',FALSE,FALSE), ('rn','Ikirundi',FALSE,FALSE), ('ro','română',FALSE,FALSE), ('ru','русский',FALSE,FALSE), ('rw','Ikinyarwanda',FALSE,FALSE), ('sg','Sängö',FALSE,FALSE), ('si','සිංහල',FALSE,FALSE), ('sk','slovenčina',FALSE,FALSE), ('sl','slovenščina',FALSE,FALSE), ('sm','sm',FALSE,FALSE), ('sn','chiShona',FALSE,FALSE), ('so','Soomaali',FALSE,FALSE), ('sq','shqip',FALSE,FALSE), ('sr','српски',FALSE,FALSE), ('ss','siSwati',FALSE,FALSE), ('st','Sesotho',FALSE,FALSE), ('sv','svenska',FALSE,FALSE), ('sw','Kiswahili',FALSE,FALSE), ('ta','தமிழ்',FALSE,FALSE), ('tet','tet',FALSE,FALSE), ('tg','тоҷикӣ',FALSE,FALSE), ('th','ไทย',FALSE,FALSE), ('ti','ትግርኛ',FALSE,FALSE), ('tk','türkmen dili',FALSE,FALSE), ('tkl','tkl',FALSE,FALSE)");
        $this->addSql("INSERT INTO symsvel_languages (iso_code, name, active, pre_open) VALUES ('tn','Setswana',FALSE,FALSE), ('to','lea fakatonga',FALSE,FALSE), ('tpi','Tok Pisin',FALSE,FALSE), ('tr','Türkçe',FALSE,FALSE), ('tvl','tvl',FALSE,FALSE), ('ty','ty',FALSE,FALSE), ('tzm','Tamaziɣt n laṭlaṣ',FALSE,FALSE), ('uk','українська',FALSE,FALSE), ('ur','اردو',FALSE,FALSE), ('uz','o‘zbek',FALSE,FALSE), ('vi','Tiếng Việt',FALSE,FALSE), ('wni','wni',FALSE,FALSE), ('wo','Wolof',FALSE,FALSE), ('yo','Èdè Yorùbá',FALSE,FALSE), ('yue','粵語',FALSE,FALSE), ('zdj','zdj',FALSE,FALSE), ('zh','中文',FALSE,FALSE)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM symsvel_languages");
    }
}
