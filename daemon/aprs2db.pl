#!/usr/bin/perl

=pod

=head1 NAME

aprs2db.pl - Save live APRS-IS feed to MySQL database

=head1 SYNOPSIS

aprs2db.pl [options]

Options:

  -h,--help        brief help message
  -s,--server      server:port (default: rotate.aprs.net:10152)
  -c,--callsign    callsign (mandatory)
  -d,--db          database (default: aprs)
  -u,--user        db user (default: aprs)

=cut

use DBI;
use Getopt::Long qw/:config auto_help/;
use Ham::APRS::IS;
use Ham::APRS::FAP qw(parseaprs);
use Pod::Usage;

sub logerr($) {
  my ($msg) = @_;
  #print STDERR "[" . gmtime() . "] $msg\n";
  print STDERR "$msg\n";
}

my $server = "rotate.aprs.net:10152";
my $callsign;
my $db = "aprs";
my $user = "aprs";
my $pass = "";

my $result = GetOptions (
  "server:s"   => \$server,
  "callsign=s" => \$callsign,
  "db=s" => \$database,
  "user=s" => \$user,
  "pass=s" => \$pass
);

die "$0: --callsign required, -h for help" if (!defined $callsign);

my $continue = 1;
$SIG{TERM} = sub { 
    $continue = 0;
    return 1;
};

my $dbh;
my $retry = 5;
while ($retry > 0 && !defined($dbh)) {
  $dbh = DBI->connect("DBI:mysql:$db", $user, $pass) or sleep(5);
  $retry--;
}

die "Could not connect to database: $DBI::errstr" if !defined($dbh);

my $passcode = Ham::APRS::IS::aprspass($callsign);
my $is = new Ham::APRS::IS($server, $callsign, 'passcode' => $passcode, 'appid' => 'aprs2db 1.0');
while ($continue) { 
    logerr("Connecting");
    $is->connect('retryuntil' => 3) || die "$is->{error}";
    logerr("Connected");

    my $l;
    while ($continue and $is->connected()) {
        $l = $is->getline_noncomment();
        last if (!defined $l);
         
        my %packetdata;
        my $retval = parseaprs($l, \%packetdata);
         
        if ($retval == 1) {
            #while (my ($key, $value) = each(%packetdata)) {
            #    print "$key: $value\n";
            #}
            #print "$packetdata{srccallsign} -- $l\n";
            $dbh->do('INSERT INTO packets (callsign, data) VALUES (?, ?)',
                undef,
                $packetdata{srccallsign},
                $l);
        } else {
            #warn "Parsing failed: $packetdata{resultmsg} ($packetdata{resultcode})\n";
        }
    }
    if (!defined $l) {
      logerr("Failed to getline: $is->{error}");
    }
    $is->disconnect() || logerr("Failed to disconnect: $is->{error}");
}
logerr("Shutting down");


