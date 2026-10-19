/**
 * Regression tests for negated / mixed / past symptom input, answer
 * pruning, shared-question de-duplication, emergency precedence and
 * distance ranking (assets/js/doctor-ak-symptom-match.js).
 *
 *     node --test "tests/js/*.test.js"
 *
 * All content below is synthetic test data, not clinical guidance.
 */
'use strict';

const test = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const Match = require( '../../assets/js/doctor-ak-symptom-match.js' );

const DURATION = { id: 'how_long', text: 'How long have you had it?', type: 'single', required: false, affects: {}, options: [ { id: 'd1', label: 'Less than a day' }, { id: 'd2', label: '1–7 days' }, { id: 'ns', label: 'Not sure' } ] };

const MAPPINGS = [
	{ id: 1, name: 'Fever', synonyms: [ 'bukhar', 'high temperature' ], specialties: [ 'general-physician' ], questions: [ DURATION ] },
	{ id: 2, name: 'Cough', synonyms: [ 'khansi' ], specialties: [ 'pulmonologist' ], questions: [
		// Same wording and answers as Fever's, but different answer ids.
		{ id: 'dur', text: 'How long have you had it?', type: 'single', required: false, affects: {}, options: [ { id: 'x1', label: 'Less than a day' }, { id: 'x2', label: '1–7 days' }, { id: 'x3', label: 'Not sure' } ] },
		{ id: 'blood', text: 'Any blood?', type: 'yesno', required: true, affects: { safety: true }, options: [ { id: 'yes', label: 'Yes' }, { id: 'no', label: 'No' } ] },
		{ id: 'colour', text: 'What colour?', type: 'single', required: false, affects: {}, options: [ { id: 'red', label: 'Red' }, { id: 'ns', label: 'Not sure' } ], show_if: { question: 'blood', answers: [ 'yes' ] } },
	] },
	{ id: 3, name: 'Headache', synonyms: [ 'sar dard' ], specialties: [ 'neurologist' ], questions: [] },
	{ id: 4, name: 'Blurred vision', synonyms: [ "can't see clearly" ], specialties: [ 'ophthalmologist' ], questions: [] },
	// No clinician-approved specialty.
	{ id: 5, name: 'Chest pain', synonyms: [ 'seene mein dard' ], specialties: [], questions: [] },
	{ id: 6, name: 'Hair loss', synonyms: [ 'baal girna' ], specialties: [ 'dermatologist' ], questions: [] },
];

const RULES = [
	{ id: 50, level: 'emergency', title: 'Emergency entry', interrupt: true, triggers: [ { symptom: 5, question: '' } ] },
	{ id: 51, level: 'emergency', title: 'Blood', interrupt: true, triggers: [ { symptom: 2, question: 'blood', answers: [ 'yes' ] } ] },
	{ id: 52, level: 'urgent', title: 'Urgent', interrupt: true, triggers: [ { symptom: 6, question: '' } ] },
];

const m = Match.create( MAPPINGS );
const names = ( ids ) => ids.map( ( id ) => m.byId[ id ].name ).sort();
const idsOf = ( list ) => list.reduce( ( all, x ) => all.concat( x.ids || x.candidates ), [] );

/* ---------------------------------------------------------------- negation */

test( '"No fever, no cough" selects and suggests nothing', () => {
	const r = m.analyse( 'No fever, no cough' );
	assert.deepEqual( r.recognized, [] );
	assert.deepEqual( r.ambiguous, [] );
	assert.deepEqual( r.confirm, [] );
	assert.deepEqual( names( idsOf( r.negated ) ), [ 'Cough', 'Fever' ] );
	assert.deepEqual( m.specialtiesFor( r.recognized ), [], 'no specialty from a denied symptom' );
} );

test( '"Cough but no fever" matches Cough only', () => {
	const r = m.analyse( 'Cough but no fever' );
	assert.deepEqual( names( r.recognized ), [ 'Cough' ] );
	assert.deepEqual( names( idsOf( r.negated ) ), [ 'Fever' ] );
} );

test( 'negation applies to its own phrase, not the whole message', () => {
	assert.deepEqual( names( m.analyse( 'cough no fever' ).recognized ), [ 'Cough' ] );
	assert.deepEqual( names( m.analyse( 'no fever, I have cough' ).recognized ), [ 'Cough' ] );
	assert.deepEqual( names( m.analyse( 'headache. No cough' ).recognized ), [ 'Headache' ] );
} );

test( 'English negation forms', () => {
	for ( const text of [ 'I don\'t have a fever', 'I do not have fever', 'I have no fever', 'without fever', 'never had fever', 'no high temperature' ] ) {
		const r = m.analyse( text );
		assert.deepEqual( r.recognized, [], text );
		assert.deepEqual( names( idsOf( r.negated ) ), [ 'Fever' ], text );
	}
} );

test( '"or" after a denial denies both; "and" after a denial is asked about', () => {
	const or = m.analyse( 'I don\'t have fever or cough' );
	assert.deepEqual( or.recognized, [] );
	assert.deepEqual( names( idsOf( or.negated ) ), [ 'Cough', 'Fever' ] );

	const and = m.analyse( 'no fever and cough' );
	assert.deepEqual( and.recognized, [], 'never silently added' );
	assert.deepEqual( and.confirm.map( ( c ) => [ c.reason, names( c.candidates ) ] ), [ [ 'unclear', [ 'Cough' ] ] ] );
} );

test( 'Roman Urdu negation: "bukhar nahi hai", mixed phrases', () => {
	const r = m.analyse( 'bukhar nahi hai' );
	assert.deepEqual( r.recognized, [] );
	assert.deepEqual( names( idsOf( r.negated ) ), [ 'Fever' ] );

	const mixed = m.analyse( 'khansi hai bukhar nahi' );
	assert.deepEqual( names( mixed.recognized ), [ 'Cough' ] );
	assert.deepEqual( names( idsOf( mixed.negated ) ), [ 'Fever' ] );

	assert.deepEqual( names( m.analyse( 'khansi hai lekin bukhar nahi hai' ).recognized ), [ 'Cough' ] );
	assert.deepEqual( names( m.analyse( 'mujhe sar dard ho raha hai' ).recognized ), [ 'Headache' ] );
} );

test( 'historical and resolved symptoms are not treated as current', () => {
	const past = m.analyse( 'I had a fever last week' );
	assert.deepEqual( past.recognized, [] );
	assert.deepEqual( past.confirm.map( ( c ) => [ c.reason, names( c.candidates ) ] ), [ [ 'past', [ 'Fever' ] ] ] );

	for ( const text of [ 'my fever went away', 'no longer have a headache', 'bukhar tha ab nahi' ] ) {
		const r = m.analyse( text );
		assert.deepEqual( r.recognized, [], text );
		assert.equal( r.resolved.length, 1, text );
	}

	assert.equal( m.analyse( 'pehle bukhar tha' ).confirm[ 0 ].reason, 'past' );
	assert.deepEqual( names( m.analyse( 'fever since 2 days' ).recognized ), [ 'Fever' ], '"since" is ongoing' );
} );

test( 'conflicting wording is asked about, not guessed', () => {
	const r = m.analyse( 'fever, no fever' );
	assert.deepEqual( r.recognized, [] );
	assert.deepEqual( names( idsOf( r.confirm ) ), [ 'Fever' ] );
} );

test( 'a symptom phrase that itself contains a negative word still matches', () => {
	assert.deepEqual( names( m.analyse( 'I can\'t see clearly' ).recognized ), [ 'Blurred vision' ] );
} );

test( 'fuzzy matches inside a denial are never added', () => {
	const r = m.analyse( 'no feverish feeling' );
	assert.deepEqual( r.recognized, [] );
	assert.deepEqual( r.ambiguous, [] );
} );

/* ---------------------------------------------------------- answer pruning */

test( 'answers to questions of removed symptoms are dropped', () => {
	const answers = { '1:how_long': 'd1', '2:blood': 'no' };
	assert.deepEqual( m.pruneAnswers( [ 1 ], answers ), { '1:how_long': 'd1' } );
	assert.deepEqual( answers, { '1:how_long': 'd1', '2:blood': 'no' }, 'input not modified' );
} );

test( 'changing an answer drops answers to questions it hides', () => {
	const answers = { '2:blood': 'yes', '2:colour': 'red' };
	assert.deepEqual( m.pruneAnswers( [ 2 ], answers ), answers );
	assert.deepEqual( m.pruneAnswers( [ 2 ], { '2:blood': 'no', '2:colour': 'red' } ), { '2:blood': 'no' } );
} );

/* ------------------------------------------------------ shared questions */

test( 'identical questions from several symptoms are asked once', () => {
	const asked = m.askList( [ 1, 2 ], {} );
	assert.deepEqual( asked.map( ( a ) => a.key ), [ '1:how_long', '2:blood' ] );
	assert.deepEqual( asked[ 0 ].symptomNames, [ 'Fever', 'Cough' ] );
	assert.deepEqual( asked[ 0 ].members.map( ( x ) => x.key ), [ '1:how_long', '2:dur' ] );
} );

test( 'a shared answer is carried to each symptom by answer position', () => {
	const asked = m.askList( [ 1, 2 ], {} );
	const [ fever, cough ] = asked[ 0 ].members;
	assert.equal( Match.mapAnswer( fever.question, cough.question, 'd2' ), 'x2' );
	assert.equal( Match.mapAnswer( fever.question, cough.question, 'ns' ), 'x3' );
	assert.deepEqual( Match.mapAnswer( fever.question, cough.question, [ 'd1', 'ns' ] ), [ 'x1', 'x3' ] );
} );

test( 'different questions are never merged', () => {
	const other = Match.create( [
		{ id: 1, name: 'A', synonyms: [], specialties: [], questions: [ { id: 'q', text: 'How long?', type: 'single', options: [ { id: 'a', label: 'Days' }, { id: 'b', label: 'Weeks' } ] } ] },
		{ id: 2, name: 'B', synonyms: [], specialties: [], questions: [ { id: 'q', text: 'How long?', type: 'single', options: [ { id: 'a', label: 'Hours' }, { id: 'b', label: 'Days' } ] } ] },
	] );
	assert.equal( other.askList( [ 1, 2 ], {} ).length, 2 );
} );

/* ------------------------------------------------------------- emergency */

test( 'emergency-entry symptoms trigger at once and take precedence over urgent rules', () => {
	const triggered = m.triggeredRules( RULES, [ 6, 5 ], {} );
	assert.deepEqual( triggered.map( ( r ) => r.id ), [ 50, 52 ] );
	assert.equal( Match.emergencyRule( triggered ).id, 50 );
	assert.equal( Match.emergencyRule( m.triggeredRules( RULES, [ 6 ], {} ) ), null, 'urgent is not emergency' );
} );

test( 'changing the answer re-evaluates the emergency rule', () => {
	assert.equal( Match.emergencyRule( m.triggeredRules( RULES, [ 2 ], { '2:blood': 'yes' } ) ).id, 51 );
	assert.equal( Match.emergencyRule( m.triggeredRules( RULES, [ 2 ], { '2:blood': 'no' } ) ), null );
	assert.equal( Match.emergencyRule( m.triggeredRules( RULES, [ 1 ], { '2:blood': 'yes' } ) ), null, 'removed symptom no longer counts' );
} );

test( 'a denied emergency symptom triggers nothing and is not a "safe" signal', () => {
	const r = m.analyse( 'no chest pain' );
	assert.deepEqual( r.recognized, [] );
	assert.deepEqual( m.triggeredRules( RULES, r.recognized, {} ), [] );
	assert.ok( ! ( 'safe' in r ), 'no "safe" outcome exists' );
} );

/* -------------------------------------------------- no approved specialty */

test( 'chest pain with no approved specialty yields no specialty and no doctors', () => {
	assert.deepEqual( m.specialtiesFor( [ 5 ] ), [] );
	const doctors = [ { id: 1, name: 'Dr P', specialties: [ 'pediatrician', 'general-surgeon' ], clinics: [ { id: 1, lat: 1, lng: 1 } ] } ];
	assert.deepEqual( Match.rankDoctors( doctors, m.specialtiesFor( [ 5 ] ), {} ).clinic, [] );
} );

test( 'with another mapped symptom, only that symptom\'s specialties are used', () => {
	assert.deepEqual( m.specialtiesFor( [ 5, 6 ] ).map( ( s ) => s.slug ), [ 'dermatologist' ] );
} );

/* ------------------------------------------------------------ distance */

test( 'distance sorting with controlled coordinates; missing coordinates last', () => {
	const origin = { lat: 24.8150, lng: 67.0310 }; // test point
	const doctors = [
		{ id: 1, name: 'Far', specialties: [ 'dermatologist' ], clinics: [ { id: 11, lat: 31.5204, lng: 74.3587 } ] },
		{ id: 2, name: 'Near', specialties: [ 'dermatologist' ], clinics: [ { id: 21, lat: 24.8138, lng: 67.0299 } ] },
		{ id: 3, name: 'No map', specialties: [ 'dermatologist' ], clinics: [ { id: 31, lat: null, lng: null } ] },
		{ id: 4, name: 'Mid', specialties: [ 'dermatologist' ], clinics: [ { id: 41, lat: 25.3792, lng: 68.3683 }, { id: 42, lat: 24.7936, lng: 67.0656 } ] },
	];
	const r = Match.rankDoctors( doctors, m.specialtiesFor( [ 6 ] ), { origin } );
	assert.deepEqual( r.clinic.map( ( e ) => e.doctor.name ), [ 'Near', 'Mid', 'Far', 'No map' ] );
	assert.equal( r.clinic[ 1 ].primary.id, 42, 'each doctor shown at their nearest clinic' );
	assert.ok( r.clinic[ 0 ].distanceKm < 1 );
	assert.equal( r.clinic[ 3 ].distanceKm, null );
	assert.ok( Math.abs( Match.haversineKm( origin, { lat: 31.5204, lng: 74.3587 } ) - 1035 ) < 15 );
} );
