eve-blueprint-library
=====================

A library for providing eve blueprint details. Both raw, and processed with character attributes. Depends on my eve-character object for passing around character data, when you're pulling out processed data, rather than just the raw figures.


Database specific code is handled by the DatabaseVendor classes, which will return arrays of relevant data to the main class, which is primarily a wrapper, with a little math bolted onto it. 
