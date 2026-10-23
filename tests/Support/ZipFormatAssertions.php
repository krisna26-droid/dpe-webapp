<?php

namespace Tests\Support;

trait ZipFormatAssertions
{
    /** Validate ZIP records independently of a reader that accepts invalid version-zero headers. */
    protected function assertStandardZip(string $bytes): array
    {
        $this->assertGreaterThan(22, strlen($bytes));
        $end = unpack('Vsignature/vdisk/vdirectoryDisk/ventriesOnDisk/ventries/VdirectorySize/VdirectoryOffset/vcommentLength', substr($bytes, -22));
        $this->assertSame(0x06054b50, $end['signature']);
        $this->assertSame(0, $end['disk']);
        $this->assertSame(0, $end['directoryDisk']);
        $this->assertSame($end['entries'], $end['entriesOnDisk']);
        $this->assertSame(0, $end['commentLength']);
        $this->assertSame(strlen($bytes) - 22, $end['directoryOffset'] + $end['directorySize']);
        $position = $end['directoryOffset'];
        $localEnd = 0;
        $parts = [];
        for ($i = 0; $i < $end['entries']; $i++) {
            $central = unpack('Vsignature/vmade/vneeded/vflags/vmethod/vtime/vdate/Vcrc/VcompressedSize/Vsize/vnameLength/vextraLength/vcommentLength/vdisk/vinternal/Vexternal/Voffset', substr($bytes, $position, 46));
            $this->assertSame(0x02014b50, $central['signature']);
            $this->assertContains($central['method'], [0, 8]);
            $this->assertGreaterThanOrEqual($central['method'] === 8 ? 20 : 10, $central['needed']);
            $this->assertGreaterThanOrEqual($central['needed'], $central['made'] & 255);
            $this->assertSame(0, $central['flags'] & 9); // no encryption or data descriptor
            $name = substr($bytes, $position + 46, $central['nameLength']);
            $this->assertArrayNotHasKey($name, $parts);
            $this->assertSame($localEnd, $central['offset']);
            $local = unpack('Vsignature/vneeded/vflags/vmethod/vtime/vdate/Vcrc/VcompressedSize/Vsize/vnameLength/vextraLength', substr($bytes, $central['offset'], 30));
            $this->assertSame(0x04034b50, $local['signature']);
            foreach (['needed', 'flags', 'method', 'time', 'date', 'crc', 'compressedSize', 'size', 'nameLength'] as $field) {
                $this->assertSame($central[$field], $local[$field], $name.' '.$field);
            }
            $this->assertSame($name, substr($bytes, $central['offset'] + 30, $local['nameLength']));
            $start = $central['offset'] + 30 + $local['nameLength'] + $local['extraLength'];
            $payload = substr($bytes, $start, $local['compressedSize']);
            $contents = $local['method'] === 8 ? gzinflate($payload) : $payload;
            $this->assertIsString($contents);
            $this->assertSame($local['size'], strlen($contents));
            $this->assertSame($local['crc'], crc32($contents));
            $parts[$name] = $contents;
            $localEnd = $start + $local['compressedSize'];
            $position += 46 + $central['nameLength'] + $central['extraLength'] + $central['commentLength'];
        }
        $this->assertSame($end['directoryOffset'], $localEnd);
        $this->assertSame($end['directoryOffset'] + $end['directorySize'], $position);

        return $parts;
    }
}
