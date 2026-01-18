<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\VehicleModel;
use App\Entity\VehicleMake;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class VehicleModelFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $modelData = [
            // Car Models
            // Toyota
            ['make' => 'Toyota', 'name' => 'Corolla', 'startYear' => 1966, 'endYear' => null],
            ['make' => 'Toyota', 'name' => 'Camry', 'startYear' => 1982, 'endYear' => null],
            ['make' => 'Toyota', 'name' => 'RAV4', 'startYear' => 1994, 'endYear' => null],
            ['make' => 'Toyota', 'name' => 'Prius', 'startYear' => 1997, 'endYear' => null],
            ['make' => 'Toyota', 'name' => 'Highlander', 'startYear' => 2000, 'endYear' => null],
            ['make' => 'Toyota', 'name' => 'Land Cruiser', 'startYear' => 1951, 'endYear' => null],
            ['make' => 'Toyota', 'name' => 'Hilux', 'startYear' => 1968, 'endYear' => null],
            ['make' => 'Toyota', 'name' => 'Yaris', 'startYear' => 1999, 'endYear' => null],
            ['make' => 'Toyota', 'name' => 'Supra', 'startYear' => 1978, 'endYear' => 2002],
            ['make' => 'Toyota', 'name' => 'Supra', 'startYear' => 2019, 'endYear' => null],
            ['make' => 'Toyota', 'name' => 'Celica', 'startYear' => 1970, 'endYear' => 2006],

            // Honda
            ['make' => 'Honda', 'name' => 'Civic', 'startYear' => 1972, 'endYear' => null],
            ['make' => 'Honda', 'name' => 'Accord', 'startYear' => 1976, 'endYear' => null],
            ['make' => 'Honda', 'name' => 'CR-V', 'startYear' => 1995, 'endYear' => null],
            ['make' => 'Honda', 'name' => 'Pilot', 'startYear' => 2002, 'endYear' => null],
            ['make' => 'Honda', 'name' => 'Fit/Jazz', 'startYear' => 2001, 'endYear' => null],
            ['make' => 'Honda', 'name' => 'Odyssey', 'startYear' => 1994, 'endYear' => null],
            ['make' => 'Honda', 'name' => 'HR-V', 'startYear' => 1998, 'endYear' => 2006],
            ['make' => 'Honda', 'name' => 'HR-V', 'startYear' => 2014, 'endYear' => null],
            ['make' => 'Honda', 'name' => 'NSX', 'startYear' => 1990, 'endYear' => 2005],
            ['make' => 'Honda', 'name' => 'NSX', 'startYear' => 2016, 'endYear' => 2022],

            // Ford
            ['make' => 'Ford', 'name' => 'F-150', 'startYear' => 1948, 'endYear' => null],
            ['make' => 'Ford', 'name' => 'Focus', 'startYear' => 1998, 'endYear' => null],
            ['make' => 'Ford', 'name' => 'Fiesta', 'startYear' => 1976, 'endYear' => null],
            ['make' => 'Ford', 'name' => 'Mustang', 'startYear' => 1964, 'endYear' => null],
            ['make' => 'Ford', 'name' => 'Explorer', 'startYear' => 1990, 'endYear' => null],
            ['make' => 'Ford', 'name' => 'Escape', 'startYear' => 2000, 'endYear' => null],
            ['make' => 'Ford', 'name' => 'Fusion', 'startYear' => 2005, 'endYear' => 2020],
            ['make' => 'Ford', 'name' => 'Ranger', 'startYear' => 1983, 'endYear' => 2011],
            ['make' => 'Ford', 'name' => 'Ranger', 'startYear' => 2019, 'endYear' => null],
            ['make' => 'Ford', 'name' => 'Bronco', 'startYear' => 1966, 'endYear' => 1996],
            ['make' => 'Ford', 'name' => 'Bronco', 'startYear' => 2021, 'endYear' => null],

            // Volkswagen
            ['make' => 'Volkswagen', 'name' => 'Golf', 'startYear' => 1974, 'endYear' => null],
            ['make' => 'Volkswagen', 'name' => 'Jetta', 'startYear' => 1979, 'endYear' => null],
            ['make' => 'Volkswagen', 'name' => 'Passat', 'startYear' => 1973, 'endYear' => null],
            ['make' => 'Volkswagen', 'name' => 'Tiguan', 'startYear' => 2007, 'endYear' => null],
            ['make' => 'Volkswagen', 'name' => 'Beetle', 'startYear' => 1938, 'endYear' => 2019],
            ['make' => 'Volkswagen', 'name' => 'Polo', 'startYear' => 1975, 'endYear' => null],
            ['make' => 'Volkswagen', 'name' => 'Touareg', 'startYear' => 2002, 'endYear' => null],
            ['make' => 'Volkswagen', 'name' => 'ID.4', 'startYear' => 2020, 'endYear' => null],

            // BMW
            ['make' => 'BMW', 'name' => '3 Series', 'startYear' => 1975, 'endYear' => null],
            ['make' => 'BMW', 'name' => '5 Series', 'startYear' => 1972, 'endYear' => null],
            ['make' => 'BMW', 'name' => '7 Series', 'startYear' => 1977, 'endYear' => null],
            ['make' => 'BMW', 'name' => 'X3', 'startYear' => 2003, 'endYear' => null],
            ['make' => 'BMW', 'name' => 'X5', 'startYear' => 1999, 'endYear' => null],
            ['make' => 'BMW', 'name' => 'M3', 'startYear' => 1986, 'endYear' => null],
            ['make' => 'BMW', 'name' => 'M5', 'startYear' => 1984, 'endYear' => null],
            ['make' => 'BMW', 'name' => 'i3', 'startYear' => 2013, 'endYear' => 2022],

            // Mercedes-Benz
            ['make' => 'Mercedes-Benz', 'name' => 'C-Class', 'startYear' => 1993, 'endYear' => null],
            ['make' => 'Mercedes-Benz', 'name' => 'E-Class', 'startYear' => 1993, 'endYear' => null],
            ['make' => 'Mercedes-Benz', 'name' => 'S-Class', 'startYear' => 1972, 'endYear' => null],
            ['make' => 'Mercedes-Benz', 'name' => 'GLE', 'startYear' => 1997, 'endYear' => null],
            ['make' => 'Mercedes-Benz', 'name' => 'GLC', 'startYear' => 2015, 'endYear' => null],
            ['make' => 'Mercedes-Benz', 'name' => 'A-Class', 'startYear' => 1997, 'endYear' => null],
            ['make' => 'Mercedes-Benz', 'name' => 'G-Class', 'startYear' => 1979, 'endYear' => null],

            // Audi
            ['make' => 'Audi', 'name' => 'A4', 'startYear' => 1994, 'endYear' => null],
            ['make' => 'Audi', 'name' => 'A6', 'startYear' => 1994, 'endYear' => null],
            ['make' => 'Audi', 'name' => 'Q5', 'startYear' => 2008, 'endYear' => null],
            ['make' => 'Audi', 'name' => 'Q7', 'startYear' => 2005, 'endYear' => null],
            ['make' => 'Audi', 'name' => 'A3', 'startYear' => 1996, 'endYear' => null],
            ['make' => 'Audi', 'name' => 'TT', 'startYear' => 1998, 'endYear' => null],
            ['make' => 'Audi', 'name' => 'e-tron', 'startYear' => 2018, 'endYear' => null],

            // Nissan
            ['make' => 'Nissan', 'name' => 'Altima', 'startYear' => 1992, 'endYear' => null],
            ['make' => 'Nissan', 'name' => 'Maxima', 'startYear' => 1981, 'endYear' => null],
            ['make' => 'Nissan', 'name' => 'Sentra', 'startYear' => 1982, 'endYear' => null],
            ['make' => 'Nissan', 'name' => 'Rogue', 'startYear' => 2007, 'endYear' => null],
            ['make' => 'Nissan', 'name' => 'Pathfinder', 'startYear' => 1985, 'endYear' => null],
            ['make' => 'Nissan', 'name' => 'GT-R', 'startYear' => 2007, 'endYear' => null],
            ['make' => 'Nissan', 'name' => 'Skyline', 'startYear' => 1957, 'endYear' => null],
            ['make' => 'Nissan', 'name' => '370Z', 'startYear' => 2009, 'endYear' => 2020],
            ['make' => 'Nissan', 'name' => 'Z', 'startYear' => 2022, 'endYear' => null],

            // Chevrolet
            ['make' => 'Chevrolet', 'name' => 'Silverado', 'startYear' => 1999, 'endYear' => null],
            ['make' => 'Chevrolet', 'name' => 'Malibu', 'startYear' => 1964, 'endYear' => null],
            ['make' => 'Chevrolet', 'name' => 'Camaro', 'startYear' => 1966, 'endYear' => null],
            ['make' => 'Chevrolet', 'name' => 'Corvette', 'startYear' => 1953, 'endYear' => null],
            ['make' => 'Chevrolet', 'name' => 'Tahoe', 'startYear' => 1995, 'endYear' => null],
            ['make' => 'Chevrolet', 'name' => 'Equinox', 'startYear' => 2004, 'endYear' => null],
            ['make' => 'Chevrolet', 'name' => 'Suburban', 'startYear' => 1935, 'endYear' => null],
            ['make' => 'Chevrolet', 'name' => 'Impala', 'startYear' => 1958, 'endYear' => 2020],

            // Hyundai
            ['make' => 'Hyundai', 'name' => 'Elantra', 'startYear' => 1990, 'endYear' => null],
            ['make' => 'Hyundai', 'name' => 'Sonata', 'startYear' => 1985, 'endYear' => null],
            ['make' => 'Hyundai', 'name' => 'Tucson', 'startYear' => 2004, 'endYear' => null],
            ['make' => 'Hyundai', 'name' => 'Santa Fe', 'startYear' => 2000, 'endYear' => null],
            ['make' => 'Hyundai', 'name' => 'Kona', 'startYear' => 2017, 'endYear' => null],
            ['make' => 'Hyundai', 'name' => 'Ioniq 5', 'startYear' => 2021, 'endYear' => null],

            // Kia
            ['make' => 'Kia', 'name' => 'Optima', 'startYear' => 2000, 'endYear' => 2020],
            ['make' => 'Kia', 'name' => 'K5', 'startYear' => 2020, 'endYear' => null],
            ['make' => 'Kia', 'name' => 'Sorento', 'startYear' => 2002, 'endYear' => null],
            ['make' => 'Kia', 'name' => 'Sportage', 'startYear' => 1993, 'endYear' => null],
            ['make' => 'Kia', 'name' => 'Telluride', 'startYear' => 2019, 'endYear' => null],
            ['make' => 'Kia', 'name' => 'Soul', 'startYear' => 2008, 'endYear' => null],
            ['make' => 'Kia', 'name' => 'EV6', 'startYear' => 2021, 'endYear' => null],

            // Mazda
            ['make' => 'Mazda', 'name' => 'Mazda3', 'startYear' => 2003, 'endYear' => null],
            ['make' => 'Mazda', 'name' => 'Mazda6', 'startYear' => 2002, 'endYear' => null],
            ['make' => 'Mazda', 'name' => 'CX-5', 'startYear' => 2012, 'endYear' => null],
            ['make' => 'Mazda', 'name' => 'CX-9', 'startYear' => 2007, 'endYear' => null],
            ['make' => 'Mazda', 'name' => 'MX-5 Miata', 'startYear' => 1989, 'endYear' => null],
            ['make' => 'Mazda', 'name' => 'RX-7', 'startYear' => 1978, 'endYear' => 2002],
            ['make' => 'Mazda', 'name' => 'RX-8', 'startYear' => 2003, 'endYear' => 2012],

            // Subaru
            ['make' => 'Subaru', 'name' => 'Outback', 'startYear' => 1994, 'endYear' => null],
            ['make' => 'Subaru', 'name' => 'Forester', 'startYear' => 1997, 'endYear' => null],
            ['make' => 'Subaru', 'name' => 'Impreza', 'startYear' => 1992, 'endYear' => null],
            ['make' => 'Subaru', 'name' => 'Legacy', 'startYear' => 1989, 'endYear' => null],
            ['make' => 'Subaru', 'name' => 'Crosstrek', 'startYear' => 2012, 'endYear' => null],
            ['make' => 'Subaru', 'name' => 'WRX', 'startYear' => 2001, 'endYear' => null],
            ['make' => 'Subaru', 'name' => 'BRZ', 'startYear' => 2012, 'endYear' => null],

            // Jeep
            ['make' => 'Jeep', 'name' => 'Wrangler', 'startYear' => 1986, 'endYear' => null],
            ['make' => 'Jeep', 'name' => 'Cherokee', 'startYear' => 1974, 'endYear' => null],
            ['make' => 'Jeep', 'name' => 'Grand Cherokee', 'startYear' => 1992, 'endYear' => null],
            ['make' => 'Jeep', 'name' => 'Compass', 'startYear' => 2006, 'endYear' => null],
            ['make' => 'Jeep', 'name' => 'Renegade', 'startYear' => 2014, 'endYear' => null],
            ['make' => 'Jeep', 'name' => 'Gladiator', 'startYear' => 2019, 'endYear' => null],

            // Tesla
            ['make' => 'Tesla', 'name' => 'Model S', 'startYear' => 2012, 'endYear' => null],
            ['make' => 'Tesla', 'name' => 'Model 3', 'startYear' => 2017, 'endYear' => null],
            ['make' => 'Tesla', 'name' => 'Model X', 'startYear' => 2015, 'endYear' => null],
            ['make' => 'Tesla', 'name' => 'Model Y', 'startYear' => 2020, 'endYear' => null],
            ['make' => 'Tesla', 'name' => 'Cybertruck', 'startYear' => 2023, 'endYear' => null],
            ['make' => 'Tesla', 'name' => 'Roadster', 'startYear' => 2008, 'endYear' => 2012],

            // Porsche
            ['make' => 'Porsche', 'name' => '911', 'startYear' => 1963, 'endYear' => null],
            ['make' => 'Porsche', 'name' => 'Cayenne', 'startYear' => 2002, 'endYear' => null],
            ['make' => 'Porsche', 'name' => 'Macan', 'startYear' => 2014, 'endYear' => null],
            ['make' => 'Porsche', 'name' => 'Panamera', 'startYear' => 2009, 'endYear' => null],
            ['make' => 'Porsche', 'name' => 'Taycan', 'startYear' => 2019, 'endYear' => null],
            ['make' => 'Porsche', 'name' => 'Boxster', 'startYear' => 1996, 'endYear' => null],
            ['make' => 'Porsche', 'name' => 'Cayman', 'startYear' => 2005, 'endYear' => null],

            // Motorcycle Models
            // Harley-Davidson
            ['make' => 'Harley-Davidson', 'name' => 'Sportster XL', 'startYear' => 1957, 'endYear' => null],
            ['make' => 'Harley-Davidson', 'name' => 'Sportster 883 (XL883)', 'startYear' => 1986, 'endYear' => null],
            ['make' => 'Harley-Davidson', 'name' => 'Sportster 1200 (XL1200)', 'startYear' => 1988, 'endYear' => null],
            ['make' => 'Harley-Davidson', 'name' => 'Sportster S (RH1250S)', 'startYear' => 2021, 'endYear' => null],
            ['make' => 'Harley-Davidson', 'name' => 'Road King (FLHR)', 'startYear' => 1994, 'endYear' => null],
            ['make' => 'Harley-Davidson', 'name' => 'Street Glide (FLHX)', 'startYear' => 2006, 'endYear' => null],
            ['make' => 'Harley-Davidson', 'name' => 'Softail Standard (FXST)', 'startYear' => 1984, 'endYear' => null],
            ['make' => 'Harley-Davidson', 'name' => 'Fat Boy (FLSTF)', 'startYear' => 1990, 'endYear' => null],
            ['make' => 'Harley-Davidson', 'name' => 'Electra Glide (FLH)', 'startYear' => 1965, 'endYear' => null],
            ['make' => 'Harley-Davidson', 'name' => 'Ultra Limited (FLHTK)', 'startYear' => 2010, 'endYear' => null],
            ['make' => 'Harley-Davidson', 'name' => 'Heritage Classic (FLHC)', 'startYear' => 1986, 'endYear' => null],
            ['make' => 'Harley-Davidson', 'name' => 'Low Rider (FXLR)', 'startYear' => 1977, 'endYear' => null],
            ['make' => 'Harley-Davidson', 'name' => 'Low Rider S (FXLRS)', 'startYear' => 2016, 'endYear' => null],
            ['make' => 'Harley-Davidson', 'name' => 'Breakout (FXBR)', 'startYear' => 2013, 'endYear' => null],
            ['make' => 'Harley-Davidson', 'name' => 'Pan America (RA1250)', 'startYear' => 2021, 'endYear' => null],
            ['make' => 'Harley-Davidson', 'name' => 'LiveWire', 'startYear' => 2019, 'endYear' => null],
            ['make' => 'Harley-Davidson', 'name' => 'Dyna Wide Glide (FXDWG)', 'startYear' => 1993, 'endYear' => 2017],
            ['make' => 'Harley-Davidson', 'name' => 'Dyna Super Glide (FXD)', 'startYear' => 1995, 'endYear' => 2017],
            ['make' => 'Harley-Davidson', 'name' => 'Dyna Street Bob (FXDB)', 'startYear' => 2006, 'endYear' => 2017],
            ['make' => 'Harley-Davidson', 'name' => 'Night Rod (VRSCD)', 'startYear' => 2006, 'endYear' => 2017],
            ['make' => 'Harley-Davidson', 'name' => 'V-Rod (VRSCA)', 'startYear' => 2002, 'endYear' => 2017],
            ['make' => 'Harley-Davidson', 'name' => 'Super Glide (FX)', 'startYear' => 1971, 'endYear' => 1984],
            ['make' => 'Harley-Davidson', 'name' => 'FXR Super Glide', 'startYear' => 1982, 'endYear' => 1994],
            ['make' => 'Harley-Davidson', 'name' => 'Shovelhead FLH', 'startYear' => 1966, 'endYear' => 1984],

            // Yamaha
            ['make' => 'Yamaha', 'name' => 'YZF-R1', 'startYear' => 1998, 'endYear' => null],
            ['make' => 'Yamaha', 'name' => 'YZF-R6', 'startYear' => 1999, 'endYear' => null],
            ['make' => 'Yamaha', 'name' => 'YZF-R3', 'startYear' => 2015, 'endYear' => null],
            ['make' => 'Yamaha', 'name' => 'YZF-R7', 'startYear' => 2021, 'endYear' => null],
            ['make' => 'Yamaha', 'name' => 'MT-07', 'startYear' => 2014, 'endYear' => null],
            ['make' => 'Yamaha', 'name' => 'MT-09', 'startYear' => 2013, 'endYear' => null],
            ['make' => 'Yamaha', 'name' => 'MT-10', 'startYear' => 2016, 'endYear' => null],
            ['make' => 'Yamaha', 'name' => 'Tenere 700', 'startYear' => 2019, 'endYear' => null],
            ['make' => 'Yamaha', 'name' => 'V-Max', 'startYear' => 1985, 'endYear' => null],
            ['make' => 'Yamaha', 'name' => 'VMAX', 'startYear' => 2009, 'endYear' => null],
            ['make' => 'Yamaha', 'name' => 'XJR1200', 'startYear' => 1994, 'endYear' => 1999],
            ['make' => 'Yamaha', 'name' => 'XJR1300', 'startYear' => 1999, 'endYear' => 2016],
            ['make' => 'Yamaha', 'name' => 'FZ-09', 'startYear' => 2014, 'endYear' => 2017],
            ['make' => 'Yamaha', 'name' => 'FZ6', 'startYear' => 2004, 'endYear' => 2009],
            ['make' => 'Yamaha', 'name' => 'FJR1300', 'startYear' => 2001, 'endYear' => null],
            ['make' => 'Yamaha', 'name' => 'Tracer 900', 'startYear' => 2015, 'endYear' => null],
            ['make' => 'Yamaha', 'name' => 'Super Tenere', 'startYear' => 2010, 'endYear' => null],
            ['make' => 'Yamaha', 'name' => 'XSR700', 'startYear' => 2016, 'endYear' => null],
            ['make' => 'Yamaha', 'name' => 'XSR900', 'startYear' => 2016, 'endYear' => null],
            ['make' => 'Yamaha', 'name' => 'Bolt', 'startYear' => 2013, 'endYear' => null],
            ['make' => 'Yamaha', 'name' => 'XS650', 'startYear' => 1970, 'endYear' => 1985],
            ['make' => 'Yamaha', 'name' => 'XS750', 'startYear' => 1976, 'endYear' => 1979],
            ['make' => 'Yamaha', 'name' => 'XS1100', 'startYear' => 1978, 'endYear' => 1981],
            ['make' => 'Yamaha', 'name' => 'RD350', 'startYear' => 1973, 'endYear' => 1975],
            ['make' => 'Yamaha', 'name' => 'RD400', 'startYear' => 1976, 'endYear' => 1979],
            ['make' => 'Yamaha', 'name' => 'TX750', 'startYear' => 1973, 'endYear' => 1974],
            ['make' => 'Yamaha', 'name' => 'SR500', 'startYear' => 1978, 'endYear' => 1999],
            ['make' => 'Yamaha', 'name' => 'XT500', 'startYear' => 1975, 'endYear' => 1989],
            ['make' => 'Yamaha', 'name' => 'DT175', 'startYear' => 1974, 'endYear' => 1983],
            ['make' => 'Yamaha', 'name' => 'RD250', 'startYear' => 1973, 'endYear' => 1979],

            // Kawasaki
            ['make' => 'Kawasaki', 'name' => 'Ninja 300', 'startYear' => 2013, 'endYear' => null],
            ['make' => 'Kawasaki', 'name' => 'Ninja 400', 'startYear' => 2018, 'endYear' => null],
            ['make' => 'Kawasaki', 'name' => 'Ninja 650', 'startYear' => 2006, 'endYear' => null],
            ['make' => 'Kawasaki', 'name' => 'Ninja 1000', 'startYear' => 2011, 'endYear' => null],
            ['make' => 'Kawasaki', 'name' => 'Ninja H2', 'startYear' => 2015, 'endYear' => null],
            ['make' => 'Kawasaki', 'name' => 'Z400', 'startYear' => 2019, 'endYear' => null],
            ['make' => 'Kawasaki', 'name' => 'Z650', 'startYear' => 2017, 'endYear' => null],
            ['make' => 'Kawasaki', 'name' => 'Z900', 'startYear' => 1972, 'endYear' => null],
            ['make' => 'Kawasaki', 'name' => 'Z1000', 'startYear' => 2003, 'endYear' => null],
            ['make' => 'Kawasaki', 'name' => 'Z1', 'startYear' => 1972, 'endYear' => 1976],
            ['make' => 'Kawasaki', 'name' => 'Versys 650', 'startYear' => 2007, 'endYear' => null],
            ['make' => 'Kawasaki', 'name' => 'Versys 1000', 'startYear' => 2012, 'endYear' => null],
            ['make' => 'Kawasaki', 'name' => 'ZX-6R', 'startYear' => 1995, 'endYear' => null],
            ['make' => 'Kawasaki', 'name' => 'ZX-7R', 'startYear' => 1989, 'endYear' => 2003],
            ['make' => 'Kawasaki', 'name' => 'ZX-9R', 'startYear' => 1994, 'endYear' => 2003],
            ['make' => 'Kawasaki', 'name' => 'ZX-10R', 'startYear' => 2004, 'endYear' => null],
            ['make' => 'Kawasaki', 'name' => 'ZX-12R', 'startYear' => 2000, 'endYear' => 2006],
            ['make' => 'Kawasaki', 'name' => 'ZX-14R', 'startYear' => 2006, 'endYear' => null],
            ['make' => 'Kawasaki', 'name' => 'ZXR750', 'startYear' => 1989, 'endYear' => 1995],
            ['make' => 'Kawasaki', 'name' => 'ZXR400', 'startYear' => 1989, 'endYear' => 2003],
            ['make' => 'Kawasaki', 'name' => 'GPZ900R Ninja', 'startYear' => 1984, 'endYear' => 2003],
            ['make' => 'Kawasaki', 'name' => 'GPZ750', 'startYear' => 1983, 'endYear' => 1987],
            ['make' => 'Kawasaki', 'name' => 'GPZ1100', 'startYear' => 1981, 'endYear' => 1985],
            ['make' => 'Kawasaki', 'name' => 'H2 Mach IV', 'startYear' => 1972, 'endYear' => 1975],
            ['make' => 'Kawasaki', 'name' => 'H1 Mach III', 'startYear' => 1969, 'endYear' => 1975],
            ['make' => 'Kawasaki', 'name' => 'Z650', 'startYear' => 1976, 'endYear' => 1983],
            ['make' => 'Kawasaki', 'name' => 'Z750', 'startYear' => 1976, 'endYear' => 1979],
            ['make' => 'Kawasaki', 'name' => 'Z1000 (Classic)', 'startYear' => 1977, 'endYear' => 1980],
            ['make' => 'Kawasaki', 'name' => 'KZ1000', 'startYear' => 1977, 'endYear' => 1983],
            ['make' => 'Kawasaki', 'name' => 'KZ900', 'startYear' => 1976, 'endYear' => 1977],
            ['make' => 'Kawasaki', 'name' => 'Vulcan S', 'startYear' => 2015, 'endYear' => null],
            ['make' => 'Kawasaki', 'name' => 'Vulcan 900', 'startYear' => 2006, 'endYear' => null],
            ['make' => 'Kawasaki', 'name' => 'Vulcan 1700', 'startYear' => 2009, 'endYear' => null],
            ['make' => 'Kawasaki', 'name' => 'KLR650', 'startYear' => 1987, 'endYear' => null],
            ['make' => 'Kawasaki', 'name' => 'KLX250', 'startYear' => 2006, 'endYear' => null],
            ['make' => 'Kawasaki', 'name' => 'KZ400', 'startYear' => 1974, 'endYear' => 1984],

            // Suzuki
            ['make' => 'Suzuki', 'name' => 'GSX-R600', 'startYear' => 1992, 'endYear' => null],
            ['make' => 'Suzuki', 'name' => 'GSX-R750', 'startYear' => 1985, 'endYear' => null],
            ['make' => 'Suzuki', 'name' => 'GSX-R1000', 'startYear' => 2001, 'endYear' => null],
            ['make' => 'Suzuki', 'name' => 'GSX-R1100', 'startYear' => 1986, 'endYear' => 1998],
            ['make' => 'Suzuki', 'name' => 'Hayabusa', 'startYear' => 1999, 'endYear' => null],
            ['make' => 'Suzuki', 'name' => 'V-Strom 650', 'startYear' => 2004, 'endYear' => null],
            ['make' => 'Suzuki', 'name' => 'V-Strom 1000', 'startYear' => 2002, 'endYear' => null],
            ['make' => 'Suzuki', 'name' => 'GSX-S1000', 'startYear' => 2015, 'endYear' => null],
            ['make' => 'Suzuki', 'name' => 'GSX-S750', 'startYear' => 2015, 'endYear' => null],
            ['make' => 'Suzuki', 'name' => 'SV650', 'startYear' => 1999, 'endYear' => null],
            ['make' => 'Suzuki', 'name' => 'Boulevard M109R', 'startYear' => 2006, 'endYear' => null],
            ['make' => 'Suzuki', 'name' => 'Boulevard C50', 'startYear' => 2005, 'endYear' => null],
            ['make' => 'Suzuki', 'name' => 'Katana', 'startYear' => 1981, 'endYear' => null],
            ['make' => 'Suzuki', 'name' => 'DR650', 'startYear' => 1990, 'endYear' => null],
            ['make' => 'Suzuki', 'name' => 'Bandit 1250', 'startYear' => 2007, 'endYear' => 2016],
            ['make' => 'Suzuki', 'name' => 'Bandit 600', 'startYear' => 1995, 'endYear' => 2004],
            ['make' => 'Suzuki', 'name' => 'GS1000', 'startYear' => 1978, 'endYear' => 1981],
            ['make' => 'Suzuki', 'name' => 'GS750', 'startYear' => 1976, 'endYear' => 1979],
            ['make' => 'Suzuki', 'name' => 'GS550', 'startYear' => 1977, 'endYear' => 1986],
            ['make' => 'Suzuki', 'name' => 'GS500', 'startYear' => 1989, 'endYear' => 2009],
            ['make' => 'Suzuki', 'name' => 'GT750', 'startYear' => 1971, 'endYear' => 1977],
            ['make' => 'Suzuki', 'name' => 'GT550', 'startYear' => 1972, 'endYear' => 1977],
            ['make' => 'Suzuki', 'name' => 'GT380', 'startYear' => 1972, 'endYear' => 1978],
            ['make' => 'Suzuki', 'name' => 'GT250', 'startYear' => 1973, 'endYear' => 1977],
            ['make' => 'Suzuki', 'name' => 'RE5 Rotary', 'startYear' => 1974, 'endYear' => 1976],

            // Honda (motorcycles)
            ['make' => 'Honda', 'name' => 'CBR300R', 'startYear' => 2015, 'endYear' => null],
            ['make' => 'Honda', 'name' => 'CBR500R', 'startYear' => 2013, 'endYear' => null],
            ['make' => 'Honda', 'name' => 'CBR600RR', 'startYear' => 2003, 'endYear' => null],
            ['make' => 'Honda', 'name' => 'CBR600F', 'startYear' => 1987, 'endYear' => 2007],
            ['make' => 'Honda', 'name' => 'CBR900RR Fireblade', 'startYear' => 1992, 'endYear' => 1999],
            ['make' => 'Honda', 'name' => 'CBR1000RR', 'startYear' => 2004, 'endYear' => null],
            ['make' => 'Honda', 'name' => 'CBR1000RR-R Fireblade', 'startYear' => 2020, 'endYear' => null],
            ['make' => 'Honda', 'name' => 'CBR1100XX Super Blackbird', 'startYear' => 1996, 'endYear' => 2007],
            ['make' => 'Honda', 'name' => 'CB300R', 'startYear' => 2018, 'endYear' => null],
            ['make' => 'Honda', 'name' => 'CB500F', 'startYear' => 2013, 'endYear' => null],
            ['make' => 'Honda', 'name' => 'CB500X', 'startYear' => 2013, 'endYear' => null],
            ['make' => 'Honda', 'name' => 'CB650R', 'startYear' => 2018, 'endYear' => null],
            ['make' => 'Honda', 'name' => 'CB1000R', 'startYear' => 2008, 'endYear' => null],
            ['make' => 'Honda', 'name' => 'CB750', 'startYear' => 1969, 'endYear' => 2003],
            ['make' => 'Honda', 'name' => 'CB900F', 'startYear' => 1979, 'endYear' => 1983],
            ['make' => 'Honda', 'name' => 'CB750F', 'startYear' => 1975, 'endYear' => 1982],
            ['make' => 'Honda', 'name' => 'CB550', 'startYear' => 1974, 'endYear' => 1978],
            ['make' => 'Honda', 'name' => 'CB400F', 'startYear' => 1975, 'endYear' => 1977],
            ['make' => 'Honda', 'name' => 'CB350', 'startYear' => 1968, 'endYear' => 1973],
            ['make' => 'Honda', 'name' => 'CX500', 'startYear' => 1978, 'endYear' => 1983],
            ['make' => 'Honda', 'name' => 'CX650', 'startYear' => 1983, 'endYear' => 1986],
            ['make' => 'Honda', 'name' => 'Gold Wing GL1000', 'startYear' => 1974, 'endYear' => 1979],
            ['make' => 'Honda', 'name' => 'Gold Wing', 'startYear' => 1980, 'endYear' => null],
            ['make' => 'Honda', 'name' => 'VFR750F', 'startYear' => 1986, 'endYear' => 1997],
            ['make' => 'Honda', 'name' => 'VFR800', 'startYear' => 1998, 'endYear' => null],
            ['make' => 'Honda', 'name' => 'Africa Twin', 'startYear' => 1988, 'endYear' => null],
            ['make' => 'Honda', 'name' => 'Rebel 300', 'startYear' => 2017, 'endYear' => null],
            ['make' => 'Honda', 'name' => 'Rebel 500', 'startYear' => 2017, 'endYear' => null],
            ['make' => 'Honda', 'name' => 'Rebel 1100', 'startYear' => 2021, 'endYear' => null],
            ['make' => 'Honda', 'name' => 'Shadow', 'startYear' => 1983, 'endYear' => null],
            ['make' => 'Honda', 'name' => 'NC750X', 'startYear' => 2014, 'endYear' => null],
            ['make' => 'Honda', 'name' => 'ST1100', 'startYear' => 1990, 'endYear' => 2002],
            ['make' => 'Honda', 'name' => 'ST1300', 'startYear' => 2002, 'endYear' => null],
            ['make' => 'Honda', 'name' => 'Grom', 'startYear' => 2013, 'endYear' => null],
            ['make' => 'Honda', 'name' => 'CB125', 'startYear' => 1971, 'endYear' => 1985],
            ['make' => 'Honda', 'name' => 'CB200', 'startYear' => 1973, 'endYear' => 1976],

            // Ducati
            ['make' => 'Ducati', 'name' => 'Panigale V2', 'startYear' => 2020, 'endYear' => null],
            ['make' => 'Ducati', 'name' => 'Panigale V4', 'startYear' => 2018, 'endYear' => null],
            ['make' => 'Ducati', 'name' => 'Panigale 959', 'startYear' => 2016, 'endYear' => 2019],
            ['make' => 'Ducati', 'name' => 'Panigale 1199', 'startYear' => 2012, 'endYear' => 2014],
            ['make' => 'Ducati', 'name' => 'Monster 797', 'startYear' => 2017, 'endYear' => 2020],
            ['make' => 'Ducati', 'name' => 'Monster 821', 'startYear' => 2014, 'endYear' => 2020],
            ['make' => 'Ducati', 'name' => 'Monster 1200', 'startYear' => 2014, 'endYear' => 2020],
            ['make' => 'Ducati', 'name' => 'Monster', 'startYear' => 1993, 'endYear' => null],
            ['make' => 'Ducati', 'name' => 'Monster+', 'startYear' => 2021, 'endYear' => null],
            ['make' => 'Ducati', 'name' => 'Multistrada 950', 'startYear' => 2017, 'endYear' => null],
            ['make' => 'Ducati', 'name' => 'Multistrada V2', 'startYear' => 2022, 'endYear' => null],
            ['make' => 'Ducati', 'name' => 'Multistrada V4', 'startYear' => 2021, 'endYear' => null],
            ['make' => 'Ducati', 'name' => 'Diavel 1260', 'startYear' => 2019, 'endYear' => null],
            ['make' => 'Ducati', 'name' => 'Diavel V4', 'startYear' => 2023, 'endYear' => null],
            ['make' => 'Ducati', 'name' => 'Scrambler Icon', 'startYear' => 2015, 'endYear' => null],
            ['make' => 'Ducati', 'name' => 'Scrambler Desert Sled', 'startYear' => 2017, 'endYear' => null],
            ['make' => 'Ducati', 'name' => 'Scrambler 1100', 'startYear' => 2018, 'endYear' => null],
            ['make' => 'Ducati', 'name' => 'SuperSport 950', 'startYear' => 2021, 'endYear' => null],
            ['make' => 'Ducati', 'name' => 'Streetfighter V2', 'startYear' => 2022, 'endYear' => null],
            ['make' => 'Ducati', 'name' => 'Streetfighter V4', 'startYear' => 2020, 'endYear' => null],
            ['make' => 'Ducati', 'name' => 'Hypermotard 950', 'startYear' => 2019, 'endYear' => null],
            ['make' => 'Ducati', 'name' => 'DesertX', 'startYear' => 2022, 'endYear' => null],

            // BMW (motorcycles)
            ['make' => 'BMW', 'name' => 'R 1250 GS', 'startYear' => 2018, 'endYear' => null],
            ['make' => 'BMW', 'name' => 'R 1250 GS Adventure', 'startYear' => 2019, 'endYear' => null],
            ['make' => 'BMW', 'name' => 'R 1200 GS', 'startYear' => 2004, 'endYear' => 2018],
            ['make' => 'BMW', 'name' => 'R 1150 GS', 'startYear' => 1999, 'endYear' => 2004],
            ['make' => 'BMW', 'name' => 'R 1100 GS', 'startYear' => 1993, 'endYear' => 1999],
            ['make' => 'BMW', 'name' => 'R 100 GS', 'startYear' => 1987, 'endYear' => 1996],
            ['make' => 'BMW', 'name' => 'R 80 G/S', 'startYear' => 1980, 'endYear' => 1987],
            ['make' => 'BMW', 'name' => 'F 900 GS', 'startYear' => 2020, 'endYear' => null],
            ['make' => 'BMW', 'name' => 'F 850 GS', 'startYear' => 2018, 'endYear' => null],
            ['make' => 'BMW', 'name' => 'F 750 GS', 'startYear' => 2018, 'endYear' => null],
            ['make' => 'BMW', 'name' => 'G 310 GS', 'startYear' => 2017, 'endYear' => null],
            ['make' => 'BMW', 'name' => 'S 1000 RR', 'startYear' => 2009, 'endYear' => null],
            ['make' => 'BMW', 'name' => 'S 1000 XR', 'startYear' => 2015, 'endYear' => null],
            ['make' => 'BMW', 'name' => 'S 1000 R', 'startYear' => 2013, 'endYear' => null],
            ['make' => 'BMW', 'name' => 'K 1600 GTL', 'startYear' => 2011, 'endYear' => null],
            ['make' => 'BMW', 'name' => 'K 1600 B', 'startYear' => 2017, 'endYear' => null],
            ['make' => 'BMW', 'name' => 'K 1200 LT', 'startYear' => 1999, 'endYear' => 2009],
            ['make' => 'BMW', 'name' => 'K 1100 LT', 'startYear' => 1991, 'endYear' => 1999],
            ['make' => 'BMW', 'name' => 'K 100', 'startYear' => 1983, 'endYear' => 1992],
            ['make' => 'BMW', 'name' => 'R nineT', 'startYear' => 2013, 'endYear' => null],
            ['make' => 'BMW', 'name' => 'R nineT Scrambler', 'startYear' => 2016, 'endYear' => null],
            ['make' => 'BMW', 'name' => 'R 18', 'startYear' => 2020, 'endYear' => null],
            ['make' => 'BMW', 'name' => 'R 75/5', 'startYear' => 1970, 'endYear' => 1973],
            ['make' => 'BMW', 'name' => 'R 90/6', 'startYear' => 1973, 'endYear' => 1976],
            ['make' => 'BMW', 'name' => 'R 100/7', 'startYear' => 1976, 'endYear' => 1984],
            ['make' => 'BMW', 'name' => 'R 100 RS', 'startYear' => 1976, 'endYear' => 1984],
            ['make' => 'BMW', 'name' => 'R 100 RT', 'startYear' => 1978, 'endYear' => 1984],
            ['make' => 'BMW', 'name' => 'R 65', 'startYear' => 1978, 'endYear' => 1985],
            ['make' => 'BMW', 'name' => 'F 900 R', 'startYear' => 2020, 'endYear' => null],
            ['make' => 'BMW', 'name' => 'F 900 XR', 'startYear' => 2020, 'endYear' => null],
            ['make' => 'BMW', 'name' => 'CE 04', 'startYear' => 2022, 'endYear' => null],

            // Triumph
            ['make' => 'Triumph', 'name' => 'Bonneville T100', 'startYear' => 2001, 'endYear' => null],
            ['make' => 'Triumph', 'name' => 'Bonneville T120', 'startYear' => 1959, 'endYear' => null],
            ['make' => 'Triumph', 'name' => 'Bonneville T140', 'startYear' => 1973, 'endYear' => 1988],
            ['make' => 'Triumph', 'name' => 'Bonneville Bobber', 'startYear' => 2017, 'endYear' => null],
            ['make' => 'Triumph', 'name' => 'Bonneville Speedmaster', 'startYear' => 2018, 'endYear' => null],
            ['make' => 'Triumph', 'name' => 'Street Triple', 'startYear' => 2007, 'endYear' => null],
            ['make' => 'Triumph', 'name' => 'Street Triple R', 'startYear' => 2013, 'endYear' => null],
            ['make' => 'Triumph', 'name' => 'Street Triple RS', 'startYear' => 2017, 'endYear' => null],
            ['make' => 'Triumph', 'name' => 'Speed Triple', 'startYear' => 1994, 'endYear' => null],
            ['make' => 'Triumph', 'name' => 'Speed Triple 1200 RS', 'startYear' => 2021, 'endYear' => null],
            ['make' => 'Triumph', 'name' => 'Tiger 900', 'startYear' => 2020, 'endYear' => null],
            ['make' => 'Triumph', 'name' => 'Tiger 1200', 'startYear' => 2022, 'endYear' => null],
            ['make' => 'Triumph', 'name' => 'Tiger 850 Sport', 'startYear' => 2021, 'endYear' => null],
            ['make' => 'Triumph', 'name' => 'Rocket 3', 'startYear' => 2004, 'endYear' => null],
            ['make' => 'Triumph', 'name' => 'Rocket 3 R', 'startYear' => 2019, 'endYear' => null],
            ['make' => 'Triumph', 'name' => 'Thruxton', 'startYear' => 2004, 'endYear' => null],
            ['make' => 'Triumph', 'name' => 'Scrambler 1200', 'startYear' => 2019, 'endYear' => null],
            ['make' => 'Triumph', 'name' => 'Scrambler 900', 'startYear' => 2017, 'endYear' => null],
            ['make' => 'Triumph', 'name' => 'Daytona 675', 'startYear' => 2006, 'endYear' => 2017],
            ['make' => 'Triumph', 'name' => 'Trident 660', 'startYear' => 2021, 'endYear' => null],
            ['make' => 'Triumph', 'name' => 'Trident T150', 'startYear' => 1968, 'endYear' => 1975],
            ['make' => 'Triumph', 'name' => 'Tiger T110', 'startYear' => 1953, 'endYear' => 1961],
            ['make' => 'Triumph', 'name' => 'Trophy TR6', 'startYear' => 1956, 'endYear' => 1973],
            ['make' => 'Triumph', 'name' => 'Thunderbird', 'startYear' => 1949, 'endYear' => 1966],
            ['make' => 'Triumph', 'name' => 'TR7 Tiger', 'startYear' => 1973, 'endYear' => 1983],

            // KTM
            ['make' => 'KTM', 'name' => '390 Duke', 'startYear' => 2013, 'endYear' => null],
            ['make' => 'KTM', 'name' => '690 Duke', 'startYear' => 2008, 'endYear' => null],
            ['make' => 'KTM', 'name' => '790 Duke', 'startYear' => 2018, 'endYear' => 2020],
            ['make' => 'KTM', 'name' => '890 Duke', 'startYear' => 2020, 'endYear' => null],
            ['make' => 'KTM', 'name' => '1290 Super Duke R', 'startYear' => 2014, 'endYear' => null],
            ['make' => 'KTM', 'name' => '1290 Super Duke GT', 'startYear' => 2016, 'endYear' => null],
            ['make' => 'KTM', 'name' => '390 Adventure', 'startYear' => 2020, 'endYear' => null],
            ['make' => 'KTM', 'name' => '890 Adventure', 'startYear' => 2020, 'endYear' => null],
            ['make' => 'KTM', 'name' => '1290 Super Adventure', 'startYear' => 2015, 'endYear' => null],
            ['make' => 'KTM', 'name' => 'RC 390', 'startYear' => 2014, 'endYear' => null],
            ['make' => 'KTM', 'name' => 'RC 125', 'startYear' => 2014, 'endYear' => null],
            ['make' => 'KTM', 'name' => '690 SMC R', 'startYear' => 2012, 'endYear' => null],
            ['make' => 'KTM', 'name' => '690 Enduro R', 'startYear' => 2009, 'endYear' => null],

            // Aprilia
            ['make' => 'Aprilia', 'name' => 'RSV4', 'startYear' => 2009, 'endYear' => null],
            ['make' => 'Aprilia', 'name' => 'RS 660', 'startYear' => 2020, 'endYear' => null],
            ['make' => 'Aprilia', 'name' => 'Tuono V4', 'startYear' => 2011, 'endYear' => null],
            ['make' => 'Aprilia', 'name' => 'Tuono 660', 'startYear' => 2021, 'endYear' => null],
            ['make' => 'Aprilia', 'name' => 'Shiver 900', 'startYear' => 2017, 'endYear' => null],
            ['make' => 'Aprilia', 'name' => 'Dorsoduro 900', 'startYear' => 2017, 'endYear' => null],

            // Royal Enfield
            ['make' => 'Royal Enfield', 'name' => 'Classic 350', 'startYear' => 2009, 'endYear' => null],
            ['make' => 'Royal Enfield', 'name' => 'Classic 500', 'startYear' => 2009, 'endYear' => 2020],
            ['make' => 'Royal Enfield', 'name' => 'Meteor 350', 'startYear' => 2020, 'endYear' => null],
            ['make' => 'Royal Enfield', 'name' => 'Interceptor 650', 'startYear' => 2018, 'endYear' => null],
            ['make' => 'Royal Enfield', 'name' => 'Continental GT 650', 'startYear' => 2018, 'endYear' => null],
            ['make' => 'Royal Enfield', 'name' => 'Himalayan', 'startYear' => 2016, 'endYear' => null],
            ['make' => 'Royal Enfield', 'name' => 'Bullet 350', 'startYear' => 1948, 'endYear' => null],

            // Indian
            ['make' => 'Indian', 'name' => 'Scout', 'startYear' => 2015, 'endYear' => null],
            ['make' => 'Indian', 'name' => 'Scout Bobber', 'startYear' => 2018, 'endYear' => null],
            ['make' => 'Indian', 'name' => 'Chief', 'startYear' => 2014, 'endYear' => null],
            ['make' => 'Indian', 'name' => 'Chieftain', 'startYear' => 2014, 'endYear' => null],
            ['make' => 'Indian', 'name' => 'Roadmaster', 'startYear' => 2015, 'endYear' => null],
            ['make' => 'Indian', 'name' => 'Springfield', 'startYear' => 2016, 'endYear' => null],
            ['make' => 'Indian', 'name' => 'FTR', 'startYear' => 2019, 'endYear' => null],
            ['make' => 'Indian', 'name' => 'Challenger', 'startYear' => 2020, 'endYear' => null],

            // Moto Guzzi
            ['make' => 'Moto Guzzi', 'name' => 'V7', 'startYear' => 2008, 'endYear' => null],
            ['make' => 'Moto Guzzi', 'name' => 'V9', 'startYear' => 2015, 'endYear' => null],
            ['make' => 'Moto Guzzi', 'name' => 'V85 TT', 'startYear' => 2019, 'endYear' => null],
            ['make' => 'Moto Guzzi', 'name' => 'V100 Mandello', 'startYear' => 2022, 'endYear' => null],
            ['make' => 'Moto Guzzi', 'name' => 'Griso', 'startYear' => 2005, 'endYear' => 2016],

            // MV Agusta
            ['make' => 'MV Agusta', 'name' => 'F3', 'startYear' => 2012, 'endYear' => null],
            ['make' => 'MV Agusta', 'name' => 'F4', 'startYear' => 1999, 'endYear' => null],
            ['make' => 'MV Agusta', 'name' => 'Brutale', 'startYear' => 2001, 'endYear' => null],
            ['make' => 'MV Agusta', 'name' => 'Dragster', 'startYear' => 2014, 'endYear' => null],
            ['make' => 'MV Agusta', 'name' => 'Turismo Veloce', 'startYear' => 2014, 'endYear' => null],
            ['make' => 'MV Agusta', 'name' => 'Superveloce', 'startYear' => 2020, 'endYear' => null],

            // Norton
            ['make' => 'Norton', 'name' => 'Commando', 'startYear' => 1967, 'endYear' => null],
            ['make' => 'Norton', 'name' => 'V4SV', 'startYear' => 2020, 'endYear' => null],
            ['make' => 'Norton', 'name' => 'Atlas', 'startYear' => 2020, 'endYear' => null],

            // AJS
            ['make' => 'AJS', 'name' => 'Cadwell', 'startYear' => 2015, 'endYear' => null],
            ['make' => 'AJS', 'name' => 'Tempest', 'startYear' => 2015, 'endYear' => null],

            // Benelli
            ['make' => 'Benelli', 'name' => 'TRK 502', 'startYear' => 2015, 'endYear' => null],
            ['make' => 'Benelli', 'name' => 'Leoncino', 'startYear' => 2017, 'endYear' => null],
            ['make' => 'Benelli', 'name' => '752S', 'startYear' => 2019, 'endYear' => null],
            ['make' => 'Benelli', 'name' => 'TNT', 'startYear' => 2006, 'endYear' => null],


            // Van Models
            // Ford
            ['make' => 'Ford', 'name' => 'Transit', 'startYear' => 1965, 'endYear' => null],
            ['make' => 'Ford', 'name' => 'Transit Connect', 'startYear' => 2002, 'endYear' => null],
            ['make' => 'Ford', 'name' => 'E-Series', 'startYear' => 1961, 'endYear' => null],

            // Mercedes-Benz
            ['make' => 'Mercedes-Benz', 'name' => 'Sprinter', 'startYear' => 1995, 'endYear' => null],
            ['make' => 'Mercedes-Benz', 'name' => 'Vito', 'startYear' => 1996, 'endYear' => null],
            ['make' => 'Mercedes-Benz', 'name' => 'Metris', 'startYear' => 2015, 'endYear' => null],

            // Volkswagen
            ['make' => 'Volkswagen', 'name' => 'Transporter', 'startYear' => 1950, 'endYear' => null],
            ['make' => 'Volkswagen', 'name' => 'Crafter', 'startYear' => 2006, 'endYear' => null],
            ['make' => 'Volkswagen', 'name' => 'Caddy', 'startYear' => 1980, 'endYear' => null],

            // Ram
            ['make' => 'Ram', 'name' => 'ProMaster', 'startYear' => 2013, 'endYear' => null],
            ['make' => 'Ram', 'name' => 'ProMaster City', 'startYear' => 2014, 'endYear' => null],

            // Chevrolet
            ['make' => 'Chevrolet', 'name' => 'Express', 'startYear' => 1996, 'endYear' => null],
            ['make' => 'Chevrolet', 'name' => 'City Express', 'startYear' => 2014, 'endYear' => 2018],

            // GMC
            ['make' => 'GMC', 'name' => 'Savana', 'startYear' => 1996, 'endYear' => null],

            // Nissan
            ['make' => 'Nissan', 'name' => 'NV', 'startYear' => 2011, 'endYear' => null],
            ['make' => 'Nissan', 'name' => 'NV200', 'startYear' => 2009, 'endYear' => null],

            // Renault
            ['make' => 'Renault', 'name' => 'Trafic', 'startYear' => 1980, 'endYear' => null],
            ['make' => 'Renault', 'name' => 'Master', 'startYear' => 1980, 'endYear' => null],
            ['make' => 'Renault', 'name' => 'Kangoo', 'startYear' => 1997, 'endYear' => null],

            // Peugeot
            ['make' => 'Peugeot', 'name' => 'Expert', 'startYear' => 1995, 'endYear' => null],
            ['make' => 'Peugeot', 'name' => 'Boxer', 'startYear' => 1994, 'endYear' => null],
            ['make' => 'Peugeot', 'name' => 'Partner', 'startYear' => 1996, 'endYear' => null],
        ];

        foreach ($modelData as $data) {
            // Find the make
            $make = $manager->getRepository(VehicleMake::class)->findOneBy(['name' => $data['make']]);

            if (!$make) {
                continue; // Skip if make doesn't exist
            }

            $model = new VehicleModel();
            $model->setName($data['name']);
            $model->setStartYear($data['startYear']);
            $model->setEndYear($data['endYear']);
            $model->setMake($make);

            $manager->persist($model);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            VehicleMakeFixtures::class,
        ];
    }
}
