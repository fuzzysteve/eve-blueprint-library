eve-blueprint-library
=====================

A library for providing eve blueprint details. Both raw, and processed with character attributes. Depends on my eve-character object for passing around character data, when you're pulling out processed data, rather than just the raw figures.


Database specific code is handled by the DatabaseVendor classes, which will return arrays of relevant data to the main class, which is primarily a wrapper, with a little math bolted onto it. 


T2 blueprints have the T1 invention details bolted onto them, for ease of use. this includes replacing the researchTechTime value with the T1 value. As I don't know of a use for it, I think we're safe to do so
