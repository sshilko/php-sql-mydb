<?php
/**
 * This file is part of the sshilko/php-sql-mydb package.
 *
 * (c) Sergei Shilko <contact@sshilko.com>
 *
 * MIT License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * @license https://opensource.org/licenses/mit-license.php MIT
 */

declare(strict_types = 1);

namespace phpunit;

use sql\MydbException;
use sql\MydbExpression;
use sql\MydbMysqliInterface;
use function explode;
use function str_replace;
use const PHP_MAJOR_VERSION;

/**
 * @author Sergei Shilko <contact@sshilko.com>
 * @license https://opensource.org/licenses/mit-license.php MIT
 *
 * @see https://github.com/sshilko/php-sql-mydb
 */
final class MetaTest extends includes\DatabaseTestCase
{
    public function testOpen(): void
    {
        $db = $this->getDefaultDb();
        $db->open();
        $actual = $db->select("SELECT 2 as n");
        self::assertSame([['n' => '2']], $actual);
        $db->close();
    }

    public function testClose(): void
    {
        $db     = $this->getDefaultDb();
        $actual = $db->select("SELECT 1 as n");
        self::assertSame([['n' => '1']], $actual);
        $db->close();
    }

    public function testPrimaryKey(): void
    {
        $db     = $this->getDefaultDb();
        $actual = $db->getPrimaryKeys('myusers');
        self::assertSame(['id'], $actual);
    }

    public function testPrimaryKeyNotFound(): void
    {
        $db     = $this->getDefaultDb();
        $actual = $db->getPrimaryKeys('mynames');
        self::assertNull($actual);
    }

    public function testPrimaryKeyComposite(): void
    {
        $db     = $this->getDefaultDb();
        $actual = $db->getPrimaryKeys('mycitynames');
        self::assertSame(['city', 'name'], $actual);
    }

    public function testEnum(): void
    {
        $db     = $this->getDefaultDb();
        $actual = $db->getEnumValues('myusers_devices', 'handler');
        self::assertSame(['1', '2', '3'], $actual);
    }

    public function testSet(): void
    {
        $db     = $this->getDefaultDb();
        $actual = $db->getEnumValues('myusers_devices', 'provider');
        self::assertSame(['Sansunk', 'Hookle', 'Sany'], $actual);
    }

    public function testNotRealEnumOrSet(): void
    {
        $db = $this->getDefaultDb();
        $this->expectException(MydbException::class);
        $this->expectExceptionMessage("Column not of type 'enum,set'");
        $db->getEnumValues('myusers_devices', 'id');
    }

    public function testEscape(): void
    {
        /**
         * @see https://www.w3.org/2001/06/utf-8-test/UTF-8-demo.html
         * @see https://www.cl.cam.ac.uk/~mgk25/ucs/examples/UTF-8-test.txt
         */
        $utf8 = <<<'ENDUTF8'
Original by Markus Kuhn, adapted for HTML by Martin Dürst.

    UTF-8 encoded sample plain-text file
‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾

Markus Kuhn [ˈmaʳkʊs kuːn] <mkuhn@acm.org> — 1999-08-20


The ASCII compatible UTF-8 encoding of ISO 10646 and Unicode
plain-text files is defined in RFC 2279 and in ISO 10646-1 Annex R.


    Using Unicode/UTF-8, you can write in emails and source code things such as

Mathematics and Sciences:

  ∮ E⋅da = Q,  n → ∞, ∑ f(i) = ∏ g(i), ∀x∈ℝ: ⌈x⌉ = −⌊−x⌋, α ∧ ¬β = ¬(¬α ∨ β),

  ℕ ⊆ ℕ₀ ⊂ ℤ ⊂ ℚ ⊂ ℝ ⊂ ℂ, ⊥ < a ≠ b ≡ c ≤ d ≪ ⊤ ⇒ (A ⇔ B),

  2H₂ + O₂ ⇌ 2H₂O, R = 4.7 kΩ, ⌀ 200 mm

Linguistics and dictionaries:

  ði ıntəˈnæʃənəl fəˈnɛtık əsoʊsiˈeıʃn
  Y [ˈʏpsilɔn], Yen [jɛn], Yoga [ˈjoːgɑ]

APL:

  ((V⍳V)=⍳⍴V)/V←,V    ⌷←⍳→⍴∆∇⊃‾⍎⍕⌈

Nicer typography in plain text files:

  ╔══════════════════════════════════════════╗
  ║                                          ║
  ║   • ‘single’ and “double” quotes         ║
  ║                                          ║
  ║   • Curly apostrophes: “We’ve been here” ║
  ║                                          ║
  ║   • Latin-1 apostrophe and accents: '´`  ║
  ║                                          ║
  ║   • ‚deutsche‘ „Anführungszeichen“       ║
  ║                                          ║
  ║   • †, ‡, ‰, •, 3–4, —, −5/+5, ™, …      ║
  ║                                          ║
  ║   • ASCII safety test: 1lI|, 0OD, 8B     ║
  ║                      ╭─────────╮         ║
  ║   • the euro symbol: │ 14.95 € │         ║
  ║                      ╰─────────╯         ║
  ╚══════════════════════════════════════════╝

Greek (in Polytonic):

  The Greek anthem:

  Σὲ γνωρίζω ἀπὸ τὴν κόψη
  τοῦ σπαθιοῦ τὴν τρομερή,
  σὲ γνωρίζω ἀπὸ τὴν ὄψη
  ποὺ μὲ βία μετράει τὴ γῆ.

  ᾿Απ᾿ τὰ κόκκαλα βγαλμένη
  τῶν ῾Ελλήνων τὰ ἱερά
  καὶ σὰν πρῶτα ἀνδρειωμένη
  χαῖρε, ὦ χαῖρε, ᾿Ελευθεριά!

  From a speech of Demosthenes in the 4th century BC:

  Οὐχὶ ταὐτὰ παρίσταταί μοι γιγνώσκειν, ὦ ἄνδρες ᾿Αθηναῖοι,
  ὅταν τ᾿ εἰς τὰ πράγματα ἀποβλέψω καὶ ὅταν πρὸς τοὺς
  λόγους οὓς ἀκούω· τοὺς μὲν γὰρ λόγους περὶ τοῦ
  τιμωρήσασθαι Φίλιππον ὁρῶ γιγνομένους, τὰ δὲ πράγματ᾿
  εἰς τοῦτο προήκοντα,  ὥσθ᾿ ὅπως μὴ πεισόμεθ᾿ αὐτοὶ
  πρότερον κακῶς σκέψασθαι δέον. οὐδέν οὖν ἄλλο μοι δοκοῦσιν
  οἱ τὰ τοιαῦτα λέγοντες ἢ τὴν ὑπόθεσιν, περὶ ἧς βουλεύεσθαι,
  οὐχὶ τὴν οὖσαν παριστάντες ὑμῖν ἁμαρτάνειν. ἐγὼ δέ, ὅτι μέν
  ποτ᾿ ἐξῆν τῇ πόλει καὶ τὰ αὑτῆς ἔχειν ἀσφαλῶς καὶ Φίλιππον
  τιμωρήσασθαι, καὶ μάλ᾿ ἀκριβῶς οἶδα· ἐπ᾿ ἐμοῦ γάρ, οὐ πάλαι
  γέγονεν ταῦτ᾿ ἀμφότερα· νῦν μέντοι πέπεισμαι τοῦθ᾿ ἱκανὸν
  προλαβεῖν ἡμῖν εἶναι τὴν πρώτην, ὅπως τοὺς συμμάχους
  σώσομεν. ἐὰν γὰρ τοῦτο βεβαίως ὑπάρξῃ, τότε καὶ περὶ τοῦ
  τίνα τιμωρήσεταί τις καὶ ὃν τρόπον ἐξέσται σκοπεῖν· πρὶν δὲ
  τὴν ἀρχὴν ὀρθῶς ὑποθέσθαι, μάταιον ἡγοῦμαι περὶ τῆς
  τελευτῆς ὁντινοῦν ποιεῖσθαι λόγον.

  Δημοσθένους, Γ´ ᾿Ολυνθιακὸς

Georgian:

  From a Unicode conference invitation:

  გთხოვთ ახლავე გაიაროთ რეგისტრაცია Unicode-ის მეათე საერთაშორისო
  კონფერენციაზე დასასწრებად, რომელიც გაიმართება 10-12 მარტს,
  ქ. მაინცში, გერმანიაში. კონფერენცია შეჰკრებს ერთად მსოფლიოს
  ექსპერტებს ისეთ დარგებში როგორიცაა ინტერნეტი და Unicode-ი,
  ინტერნაციონალიზაცია და ლოკალიზაცია, Unicode-ის გამოყენება
  ოპერაციულ სისტემებსა, და გამოყენებით პროგრამებში, შრიფტებში,
  ტექსტების დამუშავებასა და მრავალენოვან კომპიუტერულ სისტემებში.

Russian:

  From a Unicode conference invitation:

  Зарегистрируйтесь сейчас на Десятую Международную Конференцию по
  Unicode, которая состоится 10-12 марта 1997 года в Майнце в Германии.
  Конференция соберет широкий круг экспертов по  вопросам глобального
  Интернета и Unicode, локализации и интернационализации, воплощению и
  применению Unicode в различных операционных системах и программных
  приложениях, шрифтах, верстке и многоязычных компьютерных системах.

Thai (UCS Level 2):

  Excerpt from a poetry on The Romance of The Three Kingdoms (a Chinese
  classic 'San Gua'):

  [----------------------------|------------------------]
    ๏ แผ่นดินฮั่นเสื่อมโทรมแสนสังเวช  พระปกเกศกองบู๊กู้ขึ้นใหม่
  สิบสองกษัตริย์ก่อนหน้าแลถัดไป       สององค์ไซร้โง่เขลาเบาปัญญา
    ทรงนับถือขันทีเป็นที่พึ่ง           บ้านเมืองจึงวิปริตเป็นนักหนา
  โฮจิ๋นเรียกทัพทั่วหัวเมืองมา         หมายจะฆ่ามดชั่วตัวสำคัญ
    เหมือนขับไสไล่เสือจากเคหา      รับหมาป่าเข้ามาเลยอาสัญ
  ฝ่ายอ้องอุ้นยุแยกให้แตกกัน          ใช้สาวนั้นเป็นชนวนชื่นชวนใจ
    พลันลิฉุยกุยกีกลับก่อเหตุ          ช่างอาเพศจริงหนาฟ้าร้องไห้
  ต้องรบราฆ่าฟันจนบรรลัย           ฤๅหาใครค้ำชูกู้บรรลังก์ ฯ

  (The above is a two-column text. If combining characters are handled
  correctly, the lines of the second column should be aligned with the
  | character above.)

Ethiopian:

  Proverbs in the Amharic language:

  ሰማይ አይታረስ ንጉሥ አይከሰስ።
  ብላ ካለኝ እንደአባቴ በቆመጠኝ።
  ጌጥ ያለቤቱ ቁምጥና ነው።
  ደሀ በሕልሙ ቅቤ ባይጠጣ ንጣት በገደለው።
  የአፍ ወለምታ በቅቤ አይታሽም።
  አይጥ በበላ ዳዋ ተመታ።
  ሲተረጉሙ ይደረግሙ።
  ቀስ በቀስ፥ ዕንቁላል በእግሩ ይሄዳል።
  ድር ቢያብር አንበሳ ያስር።
  ሰው እንደቤቱ እንጅ እንደ ጉረቤቱ አይተዳደርም።
  እግዜር የከፈተውን ጉሮሮ ሳይዘጋው አይድርም።
  የጎረቤት ሌባ፥ ቢያዩት ይስቅ ባያዩት ያጠልቅ።
  ሥራ ከመፍታት ልጄን ላፋታት።
  ዓባይ ማደሪያ የለው፥ ግንድ ይዞ ይዞራል።
  የእስላም አገሩ መካ የአሞራ አገሩ ዋርካ።
  ተንጋሎ ቢተፉ ተመልሶ ባፉ።
  ወዳጅህ ማር ቢሆን ጨርስህ አትላሰው።
  እግርህን በፍራሽህ ልክ ዘርጋ።

Runes:

  ᚻᛖ ᚳᚹᚫᚦ ᚦᚫᛏ ᚻᛖ ᛒᚢᛞᛖ ᚩᚾ ᚦᚫᛗ ᛚᚪᚾᛞᛖ ᚾᚩᚱᚦᚹᛖᚪᚱᛞᚢᛗ ᚹᛁᚦ ᚦᚪ ᚹᛖᛥᚫ

  (Old English, which transcribed into Latin reads 'He cwaeth that he
  bude thaem lande northweardum with tha Westsae.' and means 'He said
  that he lived in the northern land near the Western Sea.')

Braille:

  ⡌⠁⠧⠑ ⠼⠁⠒  ⡍⠜⠇⠑⠹⠰⠎ ⡣⠕⠌

  ⡍⠜⠇⠑⠹ ⠺⠁⠎ ⠙⠑⠁⠙⠒ ⠞⠕ ⠃⠑⠛⠔ ⠺⠊⠹⠲ ⡹⠻⠑ ⠊⠎ ⠝⠕ ⠙⠳⠃⠞
  ⠱⠁⠞⠑⠧⠻ ⠁⠃⠳⠞ ⠹⠁⠞⠲ ⡹⠑ ⠗⠑⠛⠊⠌⠻ ⠕⠋ ⠙⠊⠎ ⠃⠥⠗⠊⠁⠇ ⠺⠁⠎
  ⠎⠊⠛⠝⠫ ⠃⠹ ⠹⠑ ⠊⠇⠻⠛⠹⠍⠁⠝⠂ ⠹⠑ ⠊⠇⠻⠅⠂ ⠹⠑ ⠥⠝⠙⠻⠞⠁⠅⠻⠂
  ⠁⠝⠙ ⠹⠑ ⠡⠊⠑⠋ ⠍⠳⠗⠝⠻⠲ ⡎⠊⠗⠕⠕⠛⠑ ⠎⠊⠛⠝⠫ ⠊⠞⠲ ⡁⠝⠙
  ⡎⠊⠗⠕⠕⠛⠑⠰⠎ ⠝⠁⠍⠑ ⠺⠁⠎ ⠛⠕⠕⠙ ⠥⠏⠕⠝ ⠰⡡⠁⠝⠛⠑⠂ ⠋⠕⠗ ⠁⠝⠹⠹⠔⠛ ⠙⠑
  ⠡⠕⠎⠑ ⠞⠕ ⠏⠥⠞ ⠙⠊⠎ ⠙⠁⠝⠙ ⠞⠕⠲

  ⡕⠇⠙ ⡍⠜⠇⠑⠹ ⠺⠁⠎ ⠁⠎ ⠙⠑⠁⠙ ⠁⠎ ⠁ ⠙⠕⠕⠗⠤⠝⠁⠊⠇⠲

  ⡍⠔⠙⠖ ⡊ ⠙⠕⠝⠰⠞ ⠍⠑⠁⠝ ⠞⠕ ⠎⠁⠹ ⠹⠁⠞ ⡊ ⠅⠝⠪⠂ ⠕⠋ ⠍⠹
  ⠪⠝ ⠅⠝⠪⠇⠫⠛⠑⠂ ⠱⠁⠞ ⠹⠻⠑ ⠊⠎ ⠏⠜⠞⠊⠊⠥⠇⠜⠇⠹ ⠙⠑⠁⠙ ⠁⠃⠳⠞
  ⠁ ⠙⠕⠕⠗⠤⠝⠁⠊⠇⠲ ⡊ ⠍⠊⠣⠞ ⠙⠁⠧⠑ ⠃⠑⠲ ⠔⠊⠇⠔⠫⠂ ⠍⠹⠎⠑⠇⠋⠂ ⠞⠕
  ⠗⠑⠛⠜⠙ ⠁ ⠊⠕⠋⠋⠔⠤⠝⠁⠊⠇ ⠁⠎ ⠹⠑ ⠙⠑⠁⠙⠑⠌ ⠏⠊⠑⠊⠑ ⠕⠋ ⠊⠗⠕⠝⠍⠕⠝⠛⠻⠹
  ⠔ ⠹⠑ ⠞⠗⠁⠙⠑⠲ ⡃⠥⠞ ⠹⠑ ⠺⠊⠎⠙⠕⠍ ⠕⠋ ⠳⠗ ⠁⠝⠊⠑⠌⠕⠗⠎
  ⠊⠎ ⠔ ⠹⠑ ⠎⠊⠍⠊⠇⠑⠆ ⠁⠝⠙ ⠍⠹ ⠥⠝⠙⠁⠇⠇⠪⠫ ⠙⠁⠝⠙⠎
  ⠩⠁⠇⠇ ⠝⠕⠞ ⠙⠊⠌⠥⠗⠃ ⠊⠞⠂ ⠕⠗ ⠹⠑ ⡊⠳⠝⠞⠗⠹⠰⠎ ⠙⠕⠝⠑ ⠋⠕⠗⠲ ⡹⠳
  ⠺⠊⠇⠇ ⠹⠻⠑⠋⠕⠗⠑ ⠏⠻⠍⠊⠞ ⠍⠑ ⠞⠕ ⠗⠑⠏⠑⠁⠞⠂ ⠑⠍⠏⠙⠁⠞⠊⠊⠁⠇⠇⠹⠂ ⠹⠁⠞
  ⡍⠜⠇⠑⠹ ⠺⠁⠎ ⠁⠎ ⠙⠑⠁⠙ ⠁⠎ ⠁ ⠙⠕⠕⠗⠤⠝⠁⠊⠇⠲

  (The first couple of paragraphs of "A Christmas Carol" by Dickens)

Compact font selection example text:

  ABCDEFGHIJKLMNOPQRSTUVWXYZ /0123456789
  abcdefghijklmnopqrstuvwxyz £©µÀÆÖÞßéöÿ
  –—‘“”„†•…‰™œŠŸž€ ΑΒΓΔΩαβγδω АБВГДабвгд
  ∀∂∈ℝ∧∪≡∞ ↑↗↨↻⇣ ┐┼╔╘░►☺♀ ﬁ�⑀₂ἠḂӥẄɐː⍎אԱა

Greetings in various languages:

  Hello world, Καλημέρα κόσμε, コンニチハ

Box drawing alignment tests:                                          █
                                                                      ▉
  ╔══╦══╗  ┌──┬──┐  ╭──┬──╮  ╭──┬──╮  ┏━━┳━━┓  ┎┒┏┑   ╷  ╻ ┏┯┓ ┌┰┐    ▊ ╱╲╱╲╳╳╳
  ║┌─╨─┐║  │╔═╧═╗│  │╒═╪═╕│  │╓─╁─╖│  ┃┌─╂─┐┃  ┗╃╄┙  ╶┼╴╺╋╸┠┼┨ ┝╋┥    ▋ ╲╱╲╱╳╳╳
  ║│╲ ╱│║  │║   ║│  ││ │ ││  │║ ┃ ║│  ┃│ ╿ │┃  ┍╅╆┓   ╵  ╹ ┗┷┛ └┸┘    ▌ ╱╲╱╲╳╳╳
  ╠╡ ╳ ╞╣  ├╢   ╟┤  ├┼─┼─┼┤  ├╫─╂─╫┤  ┣┿╾┼╼┿┫  ┕┛┖┚     ┌┄┄┐ ╎ ┏┅┅┓ ┋ ▍ ╲╱╲╱╳╳╳
  ║│╱ ╲│║  │║   ║│  ││ │ ││  │║ ┃ ║│  ┃│ ╽ │┃  ░░▒▒▓▓██ ┊  ┆ ╎ ╏  ┇ ┋ ▎
  ║└─╥─┘║  │╚═╤═╝│  │╘═╪═╛│  │╙─╀─╜│  ┃└─╂─┘┃  ░░▒▒▓▓██ ┊  ┆ ╎ ╏  ┇ ┋ ▏
  ╚══╩══╝  └──┴──┘  ╰──┴──╯  ╰──┴──╯  ┗━━┻━━┛           └╌╌┘ ╎ ┗╍╍┛ ┋  ▁▂▃▄▅▆▇█

Here come the tests:                                                          |
                                                                              |
1  Some correct UTF-8 text                                                    |
                                                                              |
You should see the Greek word 'kosme':       "κόσμε"                          |
                                                                              |
2  Boundary condition test cases                                              |
                                                                              |
2.1  First possible sequence of a certain length                              |
                                                                              |
2.1.1  1 byte  (U-00000000):        "�"
2.1.2  2 bytes (U-00000080):        ""                                       |
2.1.3  3 bytes (U-00000800):        "ࠀ"                                       |
2.1.4  4 bytes (U-00010000):        "𐀀"                                       |
2.1.5  5 bytes (U-00200000):        "�����"                                       |
2.1.6  6 bytes (U-04000000):        "������"                                       |
                                                                              |
2.2  Last possible sequence of a certain length                               |
                                                                              |
2.2.1  1 byte  (U-0000007F):        ""
2.2.2  2 bytes (U-000007FF):        "߿"                                       |
2.2.3  3 bytes (U-0000FFFF):        "￿"                                       |
2.2.4  4 bytes (U-001FFFFF):        "����"                                       |
2.2.5  5 bytes (U-03FFFFFF):        "�����"                                       |
2.2.6  6 bytes (U-7FFFFFFF):        "������"                                       |
                                                                              |
2.3  Other boundary conditions                                                |
                                                                              |
2.3.1  U-0000D7FF = ed 9f bf = "퟿"                                            |
2.3.2  U-0000E000 = ee 80 80 = ""                                            |
2.3.3  U-0000FFFD = ef bf bd = "�"                                            |
2.3.4  U-0010FFFF = f4 8f bf bf = "􏿿"                                         |
2.3.5  U-00110000 = f4 90 80 80 = "����"                                         |
                                                                              |
3  Malformed sequences                                                        |
                                                                              |
3.1  Unexpected continuation bytes                                            |
                                                                              |
Each unexpected continuation byte should be separately signalled as a         |
malformed sequence of its own.                                                |
                                                                              |
3.1.1  First continuation byte 0x80: "�"                                      |
3.1.2  Last  continuation byte 0xbf: "�"                                      |
                                                                              |
3.1.3  2 continuation bytes: "��"                                             |
3.1.4  3 continuation bytes: "���"                                            |
3.1.5  4 continuation bytes: "����"                                           |
3.1.6  5 continuation bytes: "�����"                                          |
3.1.7  6 continuation bytes: "������"                                         |
3.1.8  7 continuation bytes: "�������"                                        |
                                                                              |
3.1.9  Sequence of all 64 possible continuation bytes (0x80-0xbf):            |
                                                                              |
   "����������������                                                          |
    ����������������                                                          |
    ����������������                                                          |
    ����������������"                                                         |
                                                                              |
3.2  Lonely start characters                                                  |
                                                                              |
3.2.1  All 32 first bytes of 2-byte sequences (0xc0-0xdf),                    |
       each followed by a space character:                                    |
                                                                              |
   "� � � � � � � � � � � � � � � �                                           |
    � � � � � � � � � � � � � � � � "                                         |
                                                                              |
3.2.2  All 16 first bytes of 3-byte sequences (0xe0-0xef),                    |
       each followed by a space character:                                    |
                                                                              |
   "� � � � � � � � � � � � � � � � "                                         |
                                                                              |
3.2.3  All 8 first bytes of 4-byte sequences (0xf0-0xf7),                     |
       each followed by a space character:                                    |
                                                                              |
   "� � � � � � � � "                                                         |
                                                                              |
3.2.4  All 4 first bytes of 5-byte sequences (0xf8-0xfb),                     |
       each followed by a space character:                                    |
                                                                              |
   "� � � � "                                                                 |
                                                                              |
3.2.5  All 2 first bytes of 6-byte sequences (0xfc-0xfd),                     |
       each followed by a space character:                                    |
                                                                              |
   "� � "                                                                     |
                                                                              |
3.3  Sequences with last continuation byte missing                            |
                                                                              |
All bytes of an incomplete sequence should be signalled as a single           |
malformed sequence, i.e., you should see only a single replacement            |
character in each of the next 10 tests. (Characters as in section 2)          |
                                                                              |
3.3.1  2-byte sequence with last byte missing (U+0000):     "�"               |
3.3.2  3-byte sequence with last byte missing (U+0000):     "��"               |
3.3.3  4-byte sequence with last byte missing (U+0000):     "���"               |
3.3.4  5-byte sequence with last byte missing (U+0000):     "����"               |
3.3.5  6-byte sequence with last byte missing (U+0000):     "�����"               |
3.3.6  2-byte sequence with last byte missing (U-000007FF): "�"               |
3.3.7  3-byte sequence with last byte missing (U-0000FFFF): "�"               |
3.3.8  4-byte sequence with last byte missing (U-001FFFFF): "���"               |
3.3.9  5-byte sequence with last byte missing (U-03FFFFFF): "����"               |
3.3.10 6-byte sequence with last byte missing (U-7FFFFFFF): "�����"               |
                                                                              |
3.4  Concatenation of incomplete sequences                                    |
                                                                              |
All the 10 sequences of 3.3 concatenated, you should see 10 malformed         |
sequences being signalled:                                                    |
                                                                              |
   "�����������������������������"                                                               |
                                                                              |
3.5  Impossible bytes                                                         |
                                                                              |
The following two bytes cannot appear in a correct UTF-8 string               |
                                                                              |
3.5.1  fe = "�"                                                               |
3.5.2  ff = "�"                                                               |
3.5.3  fe fe ff ff = "����"                                                   |
                                                                              |
4  Overlong sequences                                                         |
                                                                              |
The following sequences are not malformed according to the letter of          |
the Unicode 2.0 standard. However, they are longer then necessary and         |
a correct UTF-8 encoder is not allowed to produce them. A "safe UTF-8         |
decoder" should reject them just like malformed sequences for two             |
reasons: (1) It helps to debug applications if overlong sequences are         |
not treated as valid representations of characters, because this helps        |
to spot problems more quickly. (2) Overlong sequences provide                 |
alternative representations of characters, that could maliciously be          |
used to bypass filters that check only for ASCII characters. For              |
instance, a 2-byte encoded line feed (LF) would not be caught by a            |
line counter that counts only 0x0a bytes, but it would still be               |
processed as a line feed by an unsafe UTF-8 decoder later in the              |
pipeline. From a security point of view, ASCII compatibility of UTF-8         |
sequences means also, that ASCII characters are *only* allowed to be          |
represented by ASCII bytes in the range 0x00-0x7f. To ensure this             |
aspect of ASCII compatibility, use only "safe UTF-8 decoders" that            |
reject overlong UTF-8 sequences for which a shorter encoding exists.          |
                                                                              |
4.1  Examples of an overlong ASCII character                                  |
                                                                              |
With a safe UTF-8 decoder, all of the following five overlong                 |
representations of the ASCII character slash ("/") should be rejected         |
like a malformed UTF-8 sequence, for instance by substituting it with         |
a replacement character. If you see a slash below, you do not have a          |
safe UTF-8 decoder!                                                           |
                                                                              |
4.1.1 U+002F = c0 af             = "��"                                        |
4.1.2 U+002F = e0 80 af          = "���"                                        |
4.1.3 U+002F = f0 80 80 af       = "����"                                        |
4.1.4 U+002F = f8 80 80 80 af    = "�����"                                        |
4.1.5 U+002F = fc 80 80 80 80 af = "������"                                        |
                                                                              |
4.2  Maximum overlong sequences                                               |
                                                                              |
Below you see the highest Unicode value that is still resulting in an         |
overlong sequence if represented with the given number of bytes. This         |
is a boundary test for safe UTF-8 decoders. All five characters should        |
be rejected like malformed UTF-8 sequences.                                   |
                                                                              |
4.2.1  U-0000007F = c1 bf             = "��"                                   |
4.2.2  U-000007FF = e0 9f bf          = "���"                                   |
4.2.3  U-0000FFFF = f0 8f bf bf       = "����"                                   |
4.2.4  U-001FFFFF = f8 87 bf bf bf    = "�����"                                   |
4.2.5  U-03FFFFFF = fc 83 bf bf bf bf = "������"                                   |
                                                                              |
4.3  Overlong representation of the NUL character                             |
                                                                              |
The following five sequences should also be rejected like malformed           |
UTF-8 sequences and should not be treated like the ASCII NUL                  |
character.                                                                    |
                                                                              |
4.3.1  U+0000 = c0 80             = "��"                                       |
4.3.2  U+0000 = e0 80 80          = "���"                                       |
4.3.3  U+0000 = f0 80 80 80       = "����"                                       |
4.3.4  U+0000 = f8 80 80 80 80    = "�����"                                       |
4.3.5  U+0000 = fc 80 80 80 80 80 = "������"                                       |
                                                                              |
5  Illegal code positions                                                     |
                                                                              |
The following UTF-8 sequences should be rejected like malformed               |
sequences, because they never represent valid ISO 10646 characters and        |
a UTF-8 decoder that accepts them might introduce security problems           |
comparable to overlong UTF-8 sequences.                                       |
                                                                              |
5.1 Single UTF-16 surrogates                                                  |
                                                                              |
5.1.1  U+D800 = ed a0 80 = "���"                                                |
5.1.2  U+DB7F = ed ad bf = "���"                                                |
5.1.3  U+DB80 = ed ae 80 = "���"                                                |
5.1.4  U+DBFF = ed af bf = "���"                                                |
5.1.5  U+DC00 = ed b0 80 = "���"                                                |
5.1.6  U+DF80 = ed be 80 = "���"                                                |
5.1.7  U+DFFF = ed bf bf = "���"                                                |
                                                                              |
5.2 Paired UTF-16 surrogates                                                  |
                                                                              |
5.2.1  U+D800 U+DC00 = ed a0 80 ed b0 80 = "������"                               |
5.2.2  U+D800 U+DFFF = ed a0 80 ed bf bf = "������"                               |
5.2.3  U+DB7F U+DC00 = ed ad bf ed b0 80 = "������"                               |
5.2.4  U+DB7F U+DFFF = ed ad bf ed bf bf = "������"                               |
5.2.5  U+DB80 U+DC00 = ed ae 80 ed b0 80 = "������"                               |
5.2.6  U+DB80 U+DFFF = ed ae 80 ed bf bf = "������"                               |
5.2.7  U+DBFF U+DC00 = ed af bf ed b0 80 = "������"                               |
5.2.8  U+DBFF U+DFFF = ed af bf ed bf bf = "������"                               |
                                                                              |
5.3 Noncharacter code positions                                               |
                                                                              |
The following "noncharacters" are "reserved for internal use" by              |
applications, and according to older versions of the Unicode Standard         |
"should never be interchanged". Unicode Corrigendum #9 dropped the            |
latter restriction. Nevertheless, their presence in incoming UTF-8 data       |
can remain a potential security risk, depending on what use is made of        |
these codes subsequently. Examples of such internal use:                      |
                                                                              |
 - Some file APIs with 16-bit characters may use the integer value -1         |
   = U+FFFF to signal an end-of-file (EOF) or error condition.                |
                                                                              |
 - In some UTF-16 receivers, code point U+FFFE might trigger a                |
   byte-swap operation (to convert between UTF-16LE and UTF-16BE).            |
                                                                              |
With such internal use of noncharacters, it may be desirable and safer        |
to block those code points in UTF-8 decoders, as they should never            |
occur legitimately in incoming UTF-8 data, and could trigger unsafe           |
behaviour in subsequent processing.                                           |
                                                                              |
Particularly problematic noncharacters in 16-bit applications:                |
                                                                              |
5.3.1  U+FFFE = ef bf be = "￾"                                                |
5.3.2  U+FFFF = ef bf bf = "￿"                                                |
                                                                              |
Other noncharacters:                                                          |
                                                                              |
5.3.3  U+FDD0 .. U+FDEF = "﷐﷑﷒﷓﷔﷕﷖﷗﷘﷙﷚﷛﷜﷝﷞﷟﷠﷡﷢﷣﷤﷥﷦﷧﷨﷩﷪﷫﷬﷭﷮﷯"|
                                                                              |
5.3.4  U+nFFFE U+nFFFF (for n = 1..10)                                        |
                                                                              |
       "🿾🿿𯿾𯿿𿿾𿿿񏿾񏿿񟿾񟿿񯿾񯿿񿿾񿿿򏿾򏿿                                    |
        򟿾򟿿򯿾򯿿򿿾򿿿󏿾󏿿󟿾󟿿󯿾󯿿󿿾󿿿􏿾􏿿"                                   |
                                                                              |
THE END                                                                       |
ENDUTF8;

        $input = [
            'a' => 'a',
            '1' => '1',
            '1.1' => '1.1',
            2 => '2',
            122 => '122',
            123456789 => '123456789',
            '\a' => '\\\a',
            "drop \" table" => "drop \\\" table",
            "drox \' table" => "drox \\\\\' table",
            'droc " table' => 'droc \" table',
            "droe ' table" => "droe \' table",
            " Jown's Woo's " => " Jown\'s Woo\'s ",
            "x0011" => "x0011",
            "\x0011" => "\\011",
            "\x1a" => "\\Z",
            "\r" => "\\r",
            "a\nb" => "a\\nb",
            'NULL' => 'NULL',
            'null' => 'null',
        ];

        $utf8Tests = explode("\n", $utf8);
        foreach ($utf8Tests as $t) {
            /**
             * cleanup quotes
             */
            $t         = str_replace(["'", '"'], [".", "."], $t);
            $input[$t] = $t;
        }


        $db = $this->getDefaultDb();
        $db->open();
        foreach ($input as $in => $expect) {
            $actual = $db->escape($in, '');
            self::assertSame($expect, $actual, 'Expected ' . $in . ' to be ' . $expect);
        }

        $actual = $db->escape(null);
        self::assertSame("''", $actual);

        $actual = $db->escape(new MydbExpression('hello world " unescaped null'));
        self::assertSame('hello world " unescaped null', $actual);

        $stringableObject = new class {
            /**
             * in PHP8 all classes that implement __toString automatically implement Stringable interface
             */
            public function __toString(): string
            {
                return 'NOW(123)';
            }
        };

        $actual = $db->escape($stringableObject);
        switch (PHP_MAJOR_VERSION) {
            case 7:
                self::assertSame("'NOW(123)'", $actual);

                break;
            default:
                self::assertSame('NOW(123)', $actual);

                break;
        }
    }

    public function testSingleQuotedEscape(): void
    {
        $input = [
            'aaa' => '"aaa"',
            'a b c' => '"a b c"',
            'esca\'pe' => '"esca\\\'pe"',
        ];

        $db = $this->getDefaultDb();
        foreach ($input as $in => $expect) {
            $actual = $db->escape($in, '"');
            self::assertSame($expect, $actual);
        }
    }

    public function testDoubleQuotedEscape(): void
    {
        $input = [
            'aaa' => "'aaa'",
            'a b c' => "'a b c'",
            'esca\'pe' => "'esca\\'pe'",
        ];

        $db = $this->getDefaultDb();
        foreach ($input as $in => $expect) {
            $actual = $db->escape($in, "'");
            self::assertSame($expect, $actual);
        }
    }

    public function testPrimaryKeyNotDefined(): void
    {
        $db = $this->getDefaultDb();
        $db->open();
        $db->beginTransaction();

        $db->command(
            "CREATE TABLE `myusers_nopk` (
                        `name` varchar(200) NOT NULL
                    ) ENGINE=InnoDB"
        );

        $pk = $db->getPrimaryKeys('myusers_nopk');
        self::assertNull($pk);
    }

    public function testPrimaryKeyQueryFailed(): void
    {
        $mysqli = $this->createMock(MydbMysqliInterface::class);

        $mysqli->expects(self::atLeastOnce())->method('isConnected')->willReturn(true);

        $mysqli->expects(self::once())->method('realQuery')->willReturn(false);

        $db = $this->getDefaultDb($mysqli);
        self::assertTrue($db->open());

        self::assertNull($db->getPrimaryKeys('randomtablename'));
    }
}
